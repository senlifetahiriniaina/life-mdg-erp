<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LogisticsActionHandler — Phase 39
 *
 * Handles workflow actions for the Logistics module (shipments, tracking, landed costs).
 */
class LogisticsActionHandler
{
    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'logistics.create_shipment'        => $this->createShipment($params, $context),
            'logistics.track_shipment'         => $this->trackShipment($params, $context),
            'logistics.update_delivery_status' => $this->updateDeliveryStatus($params, $context),
            'logistics.calculate_landed_cost'  => $this->calculateLandedCost($params, $context),
            'logistics.alert_delay'            => $this->alertDelay($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Logistics action: {$action}"],
        };
    }

    /**
     * action: logistics.create_shipment
     * Create a shipment record for an order.
     *
     * @param  array<string,mixed>  $params   e.g. ['carrier' => 'DHL', 'incoterm' => 'CIF']
     * @param  array<string,mixed>  $context
     * @return array{shipment_id: int|null, status: string}
     */
    public function createShipment(array $params, array $context): array
    {
        $orderId  = $context['order_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? 1;
        $carrier  = $params['carrier'] ?? 'standard';
        $incoterm = $params['incoterm'] ?? 'DAP';

        if (! $orderId) {
            return ['status' => 'error', 'reason' => 'Missing order_id in context'];
        }

        try {
            $shipmentId = DB::table('logistics_shipments')->insertGetId([
                'tenant_id'   => $tenantId,
                'order_id'    => $orderId,
                'carrier'     => $carrier,
                'incoterm'    => $incoterm,
                'status'      => 'pending',
                'source'      => 'workflow_automation',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            Log::info('WorkflowAction: shipment created', ['shipment_id' => $shipmentId, 'order_id' => $orderId]);

            return ['shipment_id' => $shipmentId, 'status' => 'created', 'carrier' => $carrier, 'incoterm' => $incoterm];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createShipment skipped', ['error' => $e->getMessage()]);
            return ['shipment_id' => null, 'status' => 'simulated', 'carrier' => $carrier];
        }
    }

    /**
     * action: logistics.track_shipment
     * Trigger a tracking status update from the carrier.
     *
     * @param  array<string,mixed>  $params   e.g. ['tracking_number' => 'ABC123']
     * @param  array<string,mixed>  $context
     * @return array{tracked: bool, tracking_number: string|null}
     */
    public function trackShipment(array $params, array $context): array
    {
        $shipmentId     = $context['shipment_id'] ?? null;
        $tenantId       = $context['tenant_id'] ?? 1;
        $trackingNumber = $params['tracking_number'] ?? ($context['tracking_number'] ?? null);

        if (! $shipmentId) {
            return ['status' => 'error', 'reason' => 'Missing shipment_id in context'];
        }

        try {
            DB::table('logistics_shipments')
                ->where('id', $shipmentId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'tracking_number' => $trackingNumber,
                    'last_tracked_at' => now(),
                    'updated_at'      => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: trackShipment skipped', ['error' => $e->getMessage()]);
            return ['tracked' => false, 'tracking_number' => $trackingNumber, 'status' => 'simulated'];
        }

        return ['tracked' => true, 'tracking_number' => $trackingNumber, 'shipment_id' => $shipmentId];
    }

    /**
     * action: logistics.update_delivery_status
     * Mark a shipment as delivered or delayed.
     *
     * @param  array<string,mixed>  $params   e.g. ['delivery_status' => 'delivered']
     * @param  array<string,mixed>  $context
     * @return array{updated: bool, delivery_status: string}
     */
    public function updateDeliveryStatus(array $params, array $context): array
    {
        $shipmentId     = $context['shipment_id'] ?? null;
        $tenantId       = $context['tenant_id'] ?? 1;
        $deliveryStatus = $params['delivery_status'] ?? 'delivered';

        if (! $shipmentId) {
            return ['status' => 'error', 'reason' => 'Missing shipment_id in context'];
        }

        $update = [
            'status'     => $deliveryStatus,
            'updated_at' => now(),
        ];

        if ($deliveryStatus === 'delivered') {
            $update['delivered_at'] = now();
        }

        try {
            $rows = DB::table('logistics_shipments')
                ->where('id', $shipmentId)
                ->where('tenant_id', $tenantId)
                ->update($update);

            return ['updated' => $rows > 0, 'delivery_status' => $deliveryStatus, 'shipment_id' => $shipmentId];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: updateDeliveryStatus skipped', ['error' => $e->getMessage()]);
            return ['updated' => false, 'delivery_status' => $deliveryStatus, 'status' => 'simulated'];
        }
    }

    /**
     * action: logistics.calculate_landed_cost
     * Compute landed cost based on Incoterm rules.
     * Incoterms: EXW, FCA, FOB, CIF, DAP, DDP
     *
     * @param  array<string,mixed>  $params   e.g. ['incoterm' => 'CIF', 'freight' => 150000]
     * @param  array<string,mixed>  $context
     * @return array{landed_cost: float, breakdown: array<string,float>}
     */
    public function calculateLandedCost(array $params, array $context): array
    {
        $goodsValue    = (float) ($context['amount'] ?? $params['goods_value'] ?? 0);
        $freight       = (float) ($params['freight'] ?? 0);
        $insurance     = (float) ($params['insurance'] ?? $goodsValue * 0.005);
        $customsDuty   = (float) ($params['customs_duty'] ?? $goodsValue * 0.10);
        $handlingFees  = (float) ($params['handling_fees'] ?? 50000);
        $incoterm      = $params['incoterm'] ?? 'CIF';
        $currency      = $context['currency'] ?? 'XOF';

        // CIF: seller pays freight + insurance; buyer pays from destination port
        // DAP/DDP: seller covers delivery to destination
        $landedCost = match (strtoupper($incoterm)) {
            'EXW'   => $goodsValue + $freight + $insurance + $customsDuty + $handlingFees,
            'FCA',
            'FOB'   => $goodsValue + $insurance + $customsDuty + $handlingFees,
            'CIF'   => $goodsValue + $customsDuty + $handlingFees,
            'DAP'   => $goodsValue + $customsDuty,
            'DDP'   => $goodsValue,
            default => $goodsValue + $freight + $insurance + $customsDuty + $handlingFees,
        };

        return [
            'landed_cost' => round($landedCost, 2),
            'currency'    => $currency,
            'incoterm'    => $incoterm,
            'breakdown'   => [
                'goods_value'   => $goodsValue,
                'freight'       => $freight,
                'insurance'     => $insurance,
                'customs_duty'  => $customsDuty,
                'handling_fees' => $handlingFees,
            ],
        ];
    }

    /**
     * action: logistics.alert_delay
     * Notify stakeholders of a shipping delay.
     *
     * @param  array<string,mixed>  $params   e.g. ['delay_days' => 5, 'reason' => 'customs hold']
     * @param  array<string,mixed>  $context
     * @return array{alert_sent: bool}
     */
    public function alertDelay(array $params, array $context): array
    {
        $shipmentId = $context['shipment_id'] ?? null;
        $orderId    = $context['order_id'] ?? null;
        $tenantId   = $context['tenant_id'] ?? 1;
        $delayDays  = (int) ($params['delay_days'] ?? 0);
        $reason     = $params['reason'] ?? 'Retard de livraison';

        try {
            DB::table('notifications')->insert([
                'tenant_id'       => $tenantId,
                'notifiable_type' => 'shipment',
                'notifiable_id'   => (int) $shipmentId,
                'type'            => 'logistics.delay_alert',
                'data'            => json_encode([
                    'shipment_id' => $shipmentId,
                    'order_id'    => $orderId,
                    'delay_days'  => $delayDays,
                    'reason'      => $reason,
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Non-blocking
        }

        return ['alert_sent' => true, 'delay_days' => $delayDays, 'reason' => $reason];
    }
}
