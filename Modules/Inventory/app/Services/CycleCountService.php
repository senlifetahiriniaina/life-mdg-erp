<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Str;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\CycleCountLine;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;

class CycleCountService
{
    public function generateCycleCount(Warehouse $warehouse, array $productIds): CycleCount
    {
        $cc = CycleCount::create([
            'reference' => 'CC-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'warehouse_id' => $warehouse->id,
            'status' => 'planned',
            'count_date' => now()->toDateString(),
        ]);

        foreach ($productIds as $productId) {
            $stocks = Stock::where('product_id', $productId)
                ->where('warehouse_id', $warehouse->id)
                ->get();

            if ($stocks->isEmpty()) {
                $cc->lines()->create([
                    'product_id' => $productId,
                    'location_id' => null,
                    'system_qty' => 0,
                    'status' => 'pending',
                ]);
            } else {
                foreach ($stocks as $stock) {
                    $cc->lines()->create([
                        'product_id' => $productId,
                        'location_id' => $stock->location_id,
                        'system_qty' => $stock->quantity,
                        'status' => 'pending',
                    ]);
                }
            }
        }

        return $cc->load('lines');
    }

    public function recordCount(CycleCountLine $line, float $qty): void
    {
        $variance = $qty - (float) $line->system_qty;
        $product = $line->product;
        $avgCost = $product
            ? (float) Stock::where('product_id', $product->id)->value('avg_cost')
            : 0.0;

        $line->update([
            'counted_qty' => $qty,
            'variance' => $variance,
            'variance_value' => round($variance * $avgCost, 2),
            'status' => 'counted',
        ]);

        // Move parent to in_progress
        $cc = $line->cycleCount;
        if ($cc && $cc->status === 'planned') {
            $cc->update(['status' => 'in_progress']);
        }
    }

    public function validateCount(CycleCount $cc): void
    {
        $cc->load('lines.product');

        foreach ($cc->lines as $line) {
            if ($line->status !== 'counted' || $line->variance === null) {
                continue;
            }

            $variance = (float) $line->variance;
            if (abs($variance) < 0.0001) {
                $line->update(['status' => 'validated']);

                continue;
            }

            // Create stock adjustment movement
            StockMovement::create([
                'product_id' => $line->product_id,
                'warehouse_id' => $cc->warehouse_id,
                'location_id' => $line->location_id,
                'type' => 'adjustment',
                'quantity' => $variance,
                'reference' => $cc->reference,
                'notes' => "Cycle count adjustment: {$cc->reference}",
            ]);

            // Adjust stock
            $stock = Stock::firstOrCreate(
                [
                    'product_id' => $line->product_id,
                    'warehouse_id' => $cc->warehouse_id,
                    'location_id' => $line->location_id,
                ],
                ['quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 0]
            );

            $stock->update(['quantity' => max(0, $stock->quantity + $variance)]);
            $line->update(['status' => 'validated']);
        }

        $cc->update(['status' => 'completed']);
    }
}
