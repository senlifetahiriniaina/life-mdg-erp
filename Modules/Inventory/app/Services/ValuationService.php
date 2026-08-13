<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\CostLayer;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ValuationRun;
use Modules\Inventory\Models\Warehouse;

class ValuationService
{
    /**
     * Create a cost layer (receipt of inventory).
     */
    public function receiveCostLayer(
        int $productId,
        int $warehouseId,
        float $qty,
        float $unitCost,
        string $method = 'fifo',
        string $reference = ''
    ): CostLayer {
        return CostLayer::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'method' => $method,
            'quantity_received' => $qty,
            'quantity_remaining' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => round($qty * $unitCost, 4),
            'received_at' => now(),
            'reference' => $reference ?: null,
            'is_exhausted' => false,
        ]);
    }

    /**
     * Consume inventory using FIFO (oldest layers first).
     * Returns total cost of consumed quantity.
     */
    public function consumeFifo(int $productId, int $warehouseId, float $qty): float
    {
        $layers = $this->getActiveLayers($productId, $warehouseId);
        $remaining = $qty;
        $totalCost = 0.0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $layer->quantity_remaining;
            $consumed = min($available, $remaining);

            $totalCost += $consumed * (float) $layer->unit_cost;
            $remaining -= $consumed;

            $layer->consume($consumed);
        }

        return round($totalCost, 4);
    }

    /**
     * Get weighted average cost for a product in a warehouse.
     */
    public function getWeightedAvgCost(int $productId, int $warehouseId): float
    {
        $layers = $this->getActiveLayers($productId, $warehouseId);

        $totalQty = 0.0;
        $totalValue = 0.0;

        foreach ($layers as $layer) {
            $qty = (float) $layer->quantity_remaining;
            $totalQty += $qty;
            $totalValue += $qty * (float) $layer->unit_cost;
        }

        if ($totalQty <= 0) {
            return 0.0;
        }

        return round($totalValue / $totalQty, 6);
    }

    /**
     * Run a full valuation for all products using the specified method.
     * Returns ValuationRun with results populated.
     */
    public function runValuation(string $name, string $method = 'fifo'): ValuationRun
    {
        // Get all distinct product+warehouse combos with active layers
        $combos = CostLayer::where('is_exhausted', false)
            ->select('product_id', 'warehouse_id')
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        $results = [];
        $totalValue = 0.0;

        foreach ($combos as $combo) {
            $layers = $this->getActiveLayers($combo->product_id, $combo->warehouse_id);

            $totalQty = 0.0;
            $totalLayerValue = 0.0;

            foreach ($layers as $layer) {
                $qty = (float) $layer->quantity_remaining;
                $totalQty += $qty;
                $totalLayerValue += $qty * (float) $layer->unit_cost;
            }

            if ($totalQty <= 0) {
                continue;
            }

            $avgUnitCost = round($totalLayerValue / $totalQty, 6);
            $productValue = round($totalLayerValue, 4);

            $product = Product::find($combo->product_id);

            $results[] = [
                'product_id' => $combo->product_id,
                'product_name' => $product?->name ?? 'Unknown',
                'warehouse_id' => $combo->warehouse_id,
                'qty' => $totalQty,
                'unit_cost' => $avgUnitCost,
                'total_value' => $productValue,
            ];

            $totalValue += $productValue;
        }

        return ValuationRun::create([
            'name' => $name,
            'method' => $method,
            'valuation_date' => now()->toDateString(),
            'status' => 'completed',
            'total_value' => round($totalValue, 4),
            'product_count' => count($results),
            'results' => $results,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Get inventory value for a specific product+warehouse.
     * Returns: {product_id, warehouse_id, quantity_on_hand, avg_unit_cost, total_value}
     */
    public function getProductValue(int $productId, int $warehouseId): array
    {
        $stock = DB::table('inventory_stock')
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $qtyOnHand = $stock ? (float) $stock->quantity : 0.0;
        $avgUnitCost = $this->getWeightedAvgCost($productId, $warehouseId);
        $totalValue = round($qtyOnHand * $avgUnitCost, 4);

        return [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity_on_hand' => $qtyOnHand,
            'avg_unit_cost' => $avgUnitCost,
            'total_value' => $totalValue,
        ];
    }

    /**
     * Get total inventory value across all products/warehouses.
     */
    public function getTotalInventoryValue(): float
    {
        $total = CostLayer::where('is_exhausted', false)
            ->selectRaw('SUM(quantity_remaining * unit_cost) as total')
            ->value('total');

        return round((float) $total, 4);
    }

    /**
     * Get valuation summary: total_value, product_count, warehouse_count, last_valuation_date
     */
    public function getValuationSummary(): array
    {
        $totalValue = $this->getTotalInventoryValue();
        $productCount = CostLayer::where('is_exhausted', false)
            ->distinct('product_id')
            ->count('product_id');
        $warehouseCount = CostLayer::where('is_exhausted', false)
            ->distinct('warehouse_id')
            ->count('warehouse_id');

        $lastRun = ValuationRun::where('status', 'completed')
            ->orderBy('valuation_date', 'desc')
            ->first();

        return [
            'total_value' => $totalValue,
            'product_count' => $productCount,
            'warehouse_count' => $warehouseCount,
            'last_valuation_date' => $lastRun?->valuation_date?->toDateString(),
        ];
    }

    /**
     * Get cost layers for a product (non-exhausted, ordered by received_at asc for FIFO).
     */
    public function getActiveLayers(int $productId, int $warehouseId): Collection
    {
        return CostLayer::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_exhausted', false)
            ->orderBy('received_at', 'asc')
            ->get();
    }

    /**
     * Get inventory value by warehouse: [{warehouse_id, warehouse_name, total_value, product_count}]
     */
    public function getValueByWarehouse(): array
    {
        $rows = DB::table('inventory_cost_layers')
            ->where('is_exhausted', false)
            ->selectRaw('warehouse_id, SUM(quantity_remaining * unit_cost) as total_value, COUNT(DISTINCT product_id) as product_count')
            ->groupBy('warehouse_id')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $warehouseId = (int) $row->warehouse_id;
            $warehouse = Warehouse::find($warehouseId);
            $result[] = [
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouse?->name ?? 'Unknown',
                'total_value' => round((float) $row->total_value, 4),
                'product_count' => (int) $row->product_count,
            ];
        }

        return $result;
    }
}
