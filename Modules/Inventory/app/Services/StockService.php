<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Modules\Inventory\Models\SKU;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;

/**
 * Stock operations for SKUs: receive/issue/adjust/transfer plus reorder helpers.
 * Stock levels are derived from immutable StockMovement records.
 */
class StockService
{
    /**
     * @throws \Exception when the SKU code already exists
     */
    public function createSKU(array $data): SKU
    {
        if (! empty($data['code']) && SKU::where('code', $data['code'])->exists()) {
            throw new \Exception("SKU code {$data['code']} already exists");
        }

        return SKU::create($data);
    }

    public function receiveStock(SKU $sku, Warehouse $warehouse, $quantity, ?string $reference = null): bool
    {
        $this->logMovement($sku, $warehouse, 'in', abs((float) $quantity), $reference);

        return true;
    }

    /**
     * @throws \Exception when there is insufficient stock to issue
     */
    public function issueStock(SKU $sku, Warehouse $warehouse, $quantity, ?string $reference = null): bool
    {
        $quantity = abs((float) $quantity);

        if ($sku->getStockInWarehouse($warehouse) < $quantity) {
            throw new \Exception('Insufficient stock to issue.');
        }

        $this->logMovement($sku, $warehouse, 'out', $quantity, $reference);

        return true;
    }

    /**
     * Adjust stock by a signed delta (positive = increase, negative = decrease).
     */
    public function adjustStock(SKU $sku, Warehouse $warehouse, $delta, ?string $reference = null): bool
    {
        $delta = (float) $delta;
        $type = $delta >= 0 ? 'in' : 'out';

        $this->logMovement($sku, $warehouse, $type, abs($delta), $reference);

        return true;
    }

    /**
     * @throws \Exception when the source warehouse has insufficient stock
     */
    public function transfer(SKU $sku, Warehouse $from, Warehouse $to, $quantity): bool
    {
        $quantity = abs((float) $quantity);

        if ($sku->getStockInWarehouse($from) < $quantity) {
            throw new \Exception('Insufficient stock to transfer.');
        }

        $this->logMovement($sku, $from, 'out', $quantity, 'Transfer to warehouse #'.$to->id);
        $this->logMovement($sku, $to, 'in', $quantity, 'Transfer from warehouse #'.$from->id);

        return true;
    }

    /**
     * Total net stock for a SKU across all warehouses.
     */
    public function getTotalStock(SKU $sku): float
    {
        $in = StockMovement::where('sku_id', $sku->id)->where('type', 'in')->sum('quantity');
        $out = StockMovement::where('sku_id', $sku->id)->where('type', 'out')->sum('quantity');

        return (float) ($in - $out);
    }

    /**
     * SKUs whose total stock is at or below their reorder level.
     */
    public function getLowStockSKUs(): Collection
    {
        return SKU::all()
            ->filter(fn (SKU $sku) => $this->getTotalStock($sku) <= (float) $sku->reorder_level)
            ->values();
    }

    public function needsReorder(SKU $sku, Warehouse $warehouse): bool
    {
        return $sku->getStockInWarehouse($warehouse) <= (float) $sku->reorder_point;
    }

    private function logMovement(SKU $sku, Warehouse $warehouse, string $type, float $quantity, ?string $reference): void
    {
        StockMovement::create([
            'sku_id' => $sku->id,
            'warehouse_id' => $warehouse->id,
            'type' => $type,
            'quantity' => $quantity,
            'reference' => $reference,
        ]);
    }
}
