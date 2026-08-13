<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\PickingWave;
use Modules\Inventory\Models\PickLine;
use Modules\Inventory\Models\Product;
use RuntimeException;

class WavePickingService
{
    /**
     * Create a new picking wave for the given order IDs.
     *
     * @param  array<int, mixed>  $orderIds
     */
    public function createWave(array $orderIds): PickingWave
    {
        return DB::transaction(function () use ($orderIds): PickingWave {
            /** @var PickingWave $wave */
            $wave = PickingWave::create([
                'status' => 'open',
                'order_ids' => $orderIds,
            ]);

            // Create pick lines from order IDs (simplified: one line per order referencing a product)
            foreach ($orderIds as $orderId) {
                $product = Product::first();
                if ($product !== null) {
                    PickLine::create([
                        'wave_id' => $wave->id,
                        'product_id' => $product->id,
                        'qty_requested' => 1,
                        'qty_picked' => 0,
                        'status' => 'pending',
                    ]);
                }
            }

            return $wave->fresh(['lines']) ?? $wave;
        });
    }

    public function startWave(PickingWave $wave): void
    {
        if ($wave->status !== 'open') {
            throw new RuntimeException("Wave #{$wave->id} cannot be started (status: {$wave->status}).");
        }

        $wave->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function confirmPick(PickLine $line, float $qty): void
    {
        $qtyRequested = (float) $line->qty_requested;

        if ($qty > $qtyRequested) {
            throw new RuntimeException("Picked quantity ({$qty}) exceeds requested ({$qtyRequested}).");
        }

        $newStatus = $qty >= $qtyRequested ? 'picked' : 'short';

        $line->update([
            'qty_picked' => $qty,
            'status' => $newStatus,
        ]);
    }

    public function completeWave(PickingWave $wave): void
    {
        if ($wave->status !== 'in_progress') {
            throw new RuntimeException("Wave #{$wave->id} cannot be completed (status: {$wave->status}).");
        }

        // Mark all still-pending lines as short
        $wave->lines()->where('status', 'pending')->update(['status' => 'short']);

        $wave->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
