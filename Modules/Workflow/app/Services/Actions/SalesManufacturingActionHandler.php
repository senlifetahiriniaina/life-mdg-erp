<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SalesManufacturingActionHandler — Phase 39
 *
 * Handles workflow actions that bridge the Sales module to the Manufacturing
 * and Inventory modules.
 *
 * All public methods: (array $params, array $context): array
 * No direct imports from Manufacturing or Inventory modules — uses DB or events.
 */
class SalesManufacturingActionHandler
{
    /**
     * Dispatch by method name (dot-notation suffix).
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $method, array $params, array $context): array
    {
        return match ($method) {
            'create_production_order'  => $this->createProductionOrder($params, $context),
            'schedule_production'      => $this->scheduleProduction($params, $context),
            'reserve_stock'            => $this->reserveStock($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Manufacturing/Inventory action: {$method}"],
        };
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * action: manufacturing.create_production_order
     *
     * Creates a manufacturing (production) order when a sales order is confirmed
     * and a BOM exists for the product.
     *
     * Context expected: order_id, product_id, quantity, delivery_date, tenant_id
     *
     * @param  array<string,mixed>  $params   e.g. ['priority' => 'normal']
     * @param  array<string,mixed>  $context
     * @return array{production_order_id: int|null, status: string}
     */
    public function createProductionOrder(array $params, array $context): array
    {
        $orderId      = $context['order_id'] ?? null;
        $productId    = $context['product_id'] ?? null;
        $quantity     = (float) ($context['quantity'] ?? 1);
        $deliveryDate = $context['delivery_date'] ?? now()->addDays(14)->toDateString();
        $tenantId     = $context['tenant_id'] ?? 1;
        $priority     = $params['priority'] ?? 'normal';

        if (! $productId) {
            return ['status' => 'error', 'reason' => 'Missing product_id in context'];
        }

        try {
            $productionOrderId = DB::table('manufacturing_production_orders')->insertGetId([
                'tenant_id'    => $tenantId,
                'sales_order_id' => $orderId,
                'product_id'   => $productId,
                'quantity'     => $quantity,
                'planned_date' => $deliveryDate,
                'status'       => 'planned',
                'priority'     => $priority,
                'source'       => 'workflow_automation',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            Log::info('WorkflowAction: created production order', [
                'production_order_id' => $productionOrderId,
                'sales_order_id'      => $orderId,
                'product_id'          => $productId,
                'quantity'            => $quantity,
            ]);

            return [
                'production_order_id' => $productionOrderId,
                'status'              => 'created',
                'product_id'          => $productId,
                'quantity'            => $quantity,
                'planned_date'        => $deliveryDate,
            ];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createProductionOrder skipped (table may not exist)', [
                'error' => $e->getMessage(),
            ]);

            return ['production_order_id' => null, 'status' => 'simulated', 'product_id' => $productId];
        }
    }

    /**
     * action: manufacturing.schedule_production
     *
     * Assigns a production order to a work centre and sets a start date.
     * Context expected: production_order_id OR (order_id + product_id), tenant_id
     *
     * @param  array<string,mixed>  $params   e.g. ['work_centre_id' => 3, 'start_offset_days' => 2]
     * @param  array<string,mixed>  $context
     * @return array{scheduled: bool}
     */
    public function scheduleProduction(array $params, array $context): array
    {
        $productionOrderId = $context['production_order_id'] ?? null;
        $tenantId          = $context['tenant_id'] ?? 1;
        $workCentreId      = $params['work_centre_id'] ?? null;
        $startOffsetDays   = (int) ($params['start_offset_days'] ?? 1);
        $startDate         = now()->addDays($startOffsetDays)->toDateString();

        if (! $productionOrderId) {
            return ['status' => 'skipped', 'reason' => 'No production_order_id in context — schedule manually'];
        }

        try {
            $rows = DB::table('manufacturing_production_orders')
                ->where('id', $productionOrderId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'work_centre_id' => $workCentreId,
                    'start_date'     => $startDate,
                    'status'         => 'scheduled',
                    'updated_at'     => now(),
                ]);

            return ['scheduled' => $rows > 0, 'start_date' => $startDate, 'work_centre_id' => $workCentreId];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: scheduleProduction skipped', ['error' => $e->getMessage()]);
            return ['scheduled' => false, 'status' => 'simulated', 'start_date' => $startDate];
        }
    }

    /**
     * action: inventory.reserve_stock
     *
     * Creates a stock reservation for a confirmed sales order.
     * Context expected: order_id, product_id, quantity, tenant_id
     *
     * @param  array<string,mixed>  $params   e.g. ['warehouse_id' => 1]
     * @param  array<string,mixed>  $context
     * @return array{reserved: bool, reservation_id: int|null}
     */
    public function reserveStock(array $params, array $context): array
    {
        $orderId     = $context['order_id'] ?? null;
        $productId   = $context['product_id'] ?? null;
        $quantity    = (float) ($context['quantity'] ?? 1);
        $tenantId    = $context['tenant_id'] ?? 1;
        $warehouseId = $params['warehouse_id'] ?? 1;

        if (! $productId) {
            return ['status' => 'error', 'reason' => 'Missing product_id for stock reservation'];
        }

        try {
            // Check available stock
            $available = (float) DB::table('inventory_stock')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->value('quantity_available') ?? 0;

            $canReserve = $available >= $quantity;

            $reservationId = DB::table('inventory_reservations')->insertGetId([
                'tenant_id'      => $tenantId,
                'order_id'       => $orderId,
                'product_id'     => $productId,
                'warehouse_id'   => $warehouseId,
                'quantity'       => $quantity,
                'status'         => $canReserve ? 'reserved' : 'partial',
                'source'         => 'workflow_automation',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            Log::info('WorkflowAction: stock reservation created', [
                'reservation_id' => $reservationId,
                'product_id'     => $productId,
                'quantity'       => $quantity,
                'can_reserve'    => $canReserve,
            ]);

            return [
                'reservation_id' => $reservationId,
                'reserved'       => $canReserve,
                'quantity'       => $quantity,
                'available'      => $available,
                'status'         => $canReserve ? 'reserved' : 'partial',
            ];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: reserveStock skipped (table may not exist)', [
                'error' => $e->getMessage(),
            ]);

            return [
                'reservation_id' => null,
                'reserved'       => false,
                'status'         => 'simulated',
                'quantity'       => $quantity,
            ];
        }
    }
}
