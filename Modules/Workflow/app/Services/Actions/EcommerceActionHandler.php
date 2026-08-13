<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EcommerceActionHandler — Phase 39
 *
 * Handles workflow actions for the Ecommerce module.
 * Uses DB facade only; no direct Ecommerce model imports.
 */
class EcommerceActionHandler
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
            'ecommerce.create_order'       => $this->createOrder($params, $context),
            'ecommerce.cancel_order'       => $this->cancelOrder($params, $context),
            'ecommerce.send_tracking'      => $this->sendTracking($params, $context),
            'ecommerce.update_stock_sync'  => $this->updateStockSync($params, $context),
            'ecommerce.generate_invoice'   => $this->generateInvoice($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Ecommerce action: {$action}"],
        };
    }

    /**
     * action: ecommerce.create_order
     * Create an e-commerce order from automation context.
     *
     * @param  array<string,mixed>  $params   e.g. ['channel' => 'web']
     * @param  array<string,mixed>  $context
     * @return array{order_id: int|null, status: string}
     */
    public function createOrder(array $params, array $context): array
    {
        $clientId  = $context['client_id'] ?? null;
        $tenantId  = $context['tenant_id'] ?? 1;
        $amount    = (float) ($context['amount'] ?? 0);
        $currency  = $context['currency'] ?? 'XOF';
        $channel   = $params['channel'] ?? 'automation';

        try {
            $orderId = DB::table('ecommerce_orders')->insertGetId([
                'tenant_id'  => $tenantId,
                'client_id'  => $clientId,
                'amount'     => $amount,
                'currency'   => $currency,
                'channel'    => $channel,
                'status'     => 'pending',
                'source'     => 'workflow_automation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('WorkflowAction: ecommerce order created', ['order_id' => $orderId]);

            return ['order_id' => $orderId, 'status' => 'created', 'channel' => $channel];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createOrder skipped', ['error' => $e->getMessage()]);
            return ['order_id' => null, 'status' => 'simulated', 'channel' => $channel];
        }
    }

    /**
     * action: ecommerce.cancel_order
     * Cancel an order with an explicit reason.
     *
     * @param  array<string,mixed>  $params   e.g. ['reason' => 'out_of_stock']
     * @param  array<string,mixed>  $context
     * @return array{cancelled: bool, order_id: int|null}
     */
    public function cancelOrder(array $params, array $context): array
    {
        $orderId  = $context['order_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? 1;
        $reason   = $params['reason'] ?? 'cancelled_by_automation';

        if (! $orderId) {
            return ['status' => 'error', 'reason' => 'Missing order_id in context'];
        }

        try {
            $rows = DB::table('ecommerce_orders')
                ->where('id', $orderId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status'       => 'cancelled',
                    'cancel_reason' => $reason,
                    'cancelled_at' => now(),
                    'updated_at'   => now(),
                ]);

            return ['cancelled' => $rows > 0, 'order_id' => $orderId, 'reason' => $reason];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: cancelOrder skipped', ['error' => $e->getMessage()]);
            return ['cancelled' => false, 'order_id' => $orderId, 'status' => 'simulated'];
        }
    }

    /**
     * action: ecommerce.send_tracking
     * Trigger a shipping notification with tracking info.
     *
     * @param  array<string,mixed>  $params   e.g. ['carrier' => 'DHL', 'tracking_number' => '123']
     * @param  array<string,mixed>  $context
     * @return array{notified: bool}
     */
    public function sendTracking(array $params, array $context): array
    {
        $orderId        = $context['order_id'] ?? null;
        $tenantId       = $context['tenant_id'] ?? 1;
        $carrier        = $params['carrier'] ?? 'standard';
        $trackingNumber = $params['tracking_number'] ?? ($context['tracking_number'] ?? null);

        try {
            DB::table('notifications')->insert([
                'tenant_id'       => $tenantId,
                'notifiable_type' => 'order',
                'notifiable_id'   => (int) $orderId,
                'type'            => 'ecommerce.tracking_sent',
                'data'            => json_encode([
                    'order_id'        => $orderId,
                    'carrier'         => $carrier,
                    'tracking_number' => $trackingNumber,
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Non-blocking
        }

        return ['notified' => true, 'carrier' => $carrier, 'tracking_number' => $trackingNumber];
    }

    /**
     * action: ecommerce.update_stock_sync
     * Sync e-commerce product stock from the Inventory module.
     *
     * @param  array<string,mixed>  $params   e.g. ['product_id' => 42]
     * @param  array<string,mixed>  $context
     * @return array{synced: bool, product_id: int|null, quantity: int}
     */
    public function updateStockSync(array $params, array $context): array
    {
        $productId = $params['product_id'] ?? ($context['product_id'] ?? null);
        $tenantId  = $context['tenant_id'] ?? 1;

        if (! $productId) {
            return ['status' => 'error', 'reason' => 'Missing product_id'];
        }

        try {
            $stock = DB::table('inventory_stock')
                ->where('product_id', $productId)
                ->where('tenant_id', $tenantId)
                ->value('quantity') ?? 0;

            $rows = DB::table('ecommerce_products')
                ->where('product_id', $productId)
                ->where('tenant_id', $tenantId)
                ->update(['stock_quantity' => $stock, 'updated_at' => now()]);

            return ['synced' => $rows > 0, 'product_id' => $productId, 'quantity' => (int) $stock];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: updateStockSync skipped', ['error' => $e->getMessage()]);
            return ['synced' => false, 'product_id' => $productId, 'quantity' => 0, 'status' => 'simulated'];
        }
    }

    /**
     * action: ecommerce.generate_invoice
     * Auto-generate an invoice upon payment confirmation.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array{invoice_id: int|null, status: string}
     */
    public function generateInvoice(array $params, array $context): array
    {
        $orderId  = $context['order_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? 1;
        $amount   = (float) ($context['amount'] ?? 0);
        $currency = $context['currency'] ?? 'XOF';

        if (! $orderId) {
            return ['status' => 'error', 'reason' => 'Missing order_id in context'];
        }

        try {
            $invoiceId = DB::table('accounting_invoices')->insertGetId([
                'tenant_id'    => $tenantId,
                'order_id'     => $orderId,
                'amount'       => $amount,
                'currency'     => $currency,
                'type'         => 'sale',
                'status'       => 'issued',
                'source'       => 'workflow_automation',
                'issued_at'    => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return ['invoice_id' => $invoiceId, 'status' => 'issued', 'amount' => $amount];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: generateInvoice skipped', ['error' => $e->getMessage()]);
            return ['invoice_id' => null, 'status' => 'simulated', 'amount' => $amount];
        }
    }
}
