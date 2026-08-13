<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Modules\Inventory\Models\RedistributionRule;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\TransferOrder;
use Modules\Inventory\Models\TransferOrderLine;
use Modules\Inventory\Models\Warehouse;

class StockRedistributionService
{
    public function createTransferOrder(
        int $fromWarehouseId,
        int $toWarehouseId,
        array $lines,
        array $meta = []
    ): TransferOrder {
        static $seq = 1;
        $reference = 'TXFR-'.now()->year.'-'.str_pad((string) $seq++, 5, '0', STR_PAD_LEFT);

        $order = TransferOrder::create([
            'reference' => $reference,
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'status' => $meta['status'] ?? 'draft',
            'type' => $meta['type'] ?? 'manual',
            'priority' => $meta['priority'] ?? 'normal',
            'requested_by' => $meta['requested_by'] ?? null,
            'expected_delivery_date' => $meta['expected_delivery_date'] ?? null,
            'notes' => $meta['notes'] ?? null,
            'total_items' => count($lines),
            'total_value' => 0,
        ]);

        $totalValue = 0.0;
        foreach ($lines as $line) {
            $orderLine = TransferOrderLine::create([
                'transfer_order_id' => $order->id,
                'product_id' => $line['product_id'],
                'requested_quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'] ?? 0,
                'notes' => $line['notes'] ?? null,
            ]);
            $totalValue += $orderLine->lineValue();
        }

        $order->update(['total_value' => $totalValue]);

        return $order->load('lines');
    }

    public function approveTransfer(TransferOrder $order, int $approvedByUserId): TransferOrder
    {
        $order->update([
            'status' => 'approved',
            'approved_by' => $approvedByUserId,
            'approved_at' => now(),
        ]);

        foreach ($order->lines as $line) {
            $line->update(['approved_quantity' => $line->requested_quantity]);
        }

        return $order->fresh();
    }

    public function shipTransfer(TransferOrder $order): TransferOrder
    {
        $order->update([
            'status' => 'in_transit',
            'shipped_at' => now(),
        ]);

        foreach ($order->lines as $line) {
            $qty = (float) ($line->approved_quantity ?? $line->requested_quantity);
            $line->update(['shipped_quantity' => $qty]);
        }

        return $order->fresh();
    }

    public function receiveTransfer(TransferOrder $order, array $receivedQuantities): TransferOrder
    {
        foreach ($order->lines as $line) {
            $received = $receivedQuantities[$line->id] ?? (float) $line->shipped_quantity;
            $line->update(['received_quantity' => $received]);
        }

        $order->update([
            'status' => 'received',
            'received_at' => now(),
        ]);

        return $order->fresh();
    }

    public function cancelTransfer(TransferOrder $order): TransferOrder
    {
        $order->update(['status' => 'cancelled']);

        return $order->fresh();
    }

    public function autoSuggestTransfers(): array
    {
        $rules = RedistributionRule::where('is_active', true)->orderBy('priority')->get();
        $created = [];

        foreach ($rules as $rule) {
            if (! $rule->from_warehouse_id || ! $rule->to_warehouse_id) {
                continue;
            }

            $query = Stock::query()
                ->where('warehouse_id', $rule->from_warehouse_id);

            if ($rule->product_id) {
                $query->where('product_id', $rule->product_id);
            }

            $stocks = $query->get();

            foreach ($stocks as $stock) {
                if ($rule->shouldTrigger((float) $stock->quantity)) {
                    $transfer = $this->createTransferOrder(
                        $rule->from_warehouse_id,
                        $rule->to_warehouse_id,
                        [[
                            'product_id' => $stock->product_id,
                            'quantity' => (float) $rule->transfer_quantity,
                            'unit_cost' => (float) $stock->avg_cost,
                        ]],
                        ['type' => 'auto_replenishment']
                    );
                    $created[] = $transfer;
                }
            }
        }

        return ['created' => count($created), 'transfers' => $created];
    }

    public function rebalancingAnalysis(): array
    {
        $warehouses = Warehouse::all()->keyBy('id');
        $stocks = Stock::with('product')->get()->groupBy('product_id');
        $result = [];

        foreach ($stocks as $productId => $productStocks) {
            $product = $productStocks->first()->product;
            $wData = [];
            $total = 0.0;

            foreach ($productStocks as $s) {
                $qty = (float) $s->quantity;
                $total += $qty;
                $wData[] = [
                    'warehouse_id' => $s->warehouse_id,
                    'warehouse_name' => $warehouses[$s->warehouse_id]->name ?? 'Unknown',
                    'stock' => $qty,
                    'avg_cost' => (float) $s->avg_cost,
                ];
            }

            $avg = count($wData) > 0 ? $total / count($wData) : 0;
            $rebalanceNeeded = false;

            foreach ($wData as &$w) {
                $w['overflow'] = max(0, $w['stock'] - $avg * 1.3);
                $w['deficit'] = max(0, $avg * 0.7 - $w['stock']);
                $rebalanceNeeded = $rebalanceNeeded || $w['overflow'] > 0 || $w['deficit'] > 0;
            }

            $result[] = [
                'product_id' => $productId,
                'product_name' => $product?->name ?? 'Unknown',
                'total_stock' => $total,
                'avg_per_warehouse' => round($avg, 4),
                'warehouses' => $wData,
                'rebalance_needed' => $rebalanceNeeded,
            ];
        }

        return $result;
    }

    public function warehouseTransferHistory(int $warehouseId, int $limit = 50): array
    {
        $orders = TransferOrder::where('from_warehouse_id', $warehouseId)
            ->orWhere('to_warehouse_id', $warehouseId)
            ->with(['fromWarehouse', 'toWarehouse'])
            ->latest()
            ->limit($limit)
            ->get();

        return $orders->map(fn (TransferOrder $o) => [
            'id' => $o->id,
            'reference' => $o->reference,
            'direction' => $o->from_warehouse_id === $warehouseId ? 'outbound' : 'inbound',
            'other_warehouse' => $o->from_warehouse_id === $warehouseId
                ? ($o->toWarehouse?->name)
                : ($o->fromWarehouse?->name),
            'status' => $o->status,
            'total_value' => (float) $o->total_value,
            'created_at' => $o->created_at->toIso8601String(),
        ])->toArray();
    }

    public function pendingTransfers(): Collection
    {
        return TransferOrder::whereIn('status', ['pending_approval', 'approved', 'in_transit'])
            ->with(['fromWarehouse', 'toWarehouse'])
            ->latest()
            ->get();
    }
}
