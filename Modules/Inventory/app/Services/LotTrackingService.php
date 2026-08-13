<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotMovement;

class LotTrackingService
{
    public function createLot(array $data): Lot
    {
        return Lot::create($data);
    }

    public function receiveLot(Lot $lot, float $qty, string $reference = ''): LotMovement
    {
        $lot->increment('quantity', $qty);
        $lot->refresh();

        return LotMovement::create([
            'lot_id' => $lot->id,
            'movement_type' => 'receipt',
            'quantity' => $qty,
            'reference' => $reference ?: null,
        ]);
    }

    public function issueLot(Lot $lot, float $qty, string $reference = ''): LotMovement
    {
        if ($qty > (float) $lot->quantity) {
            throw new \InvalidArgumentException(
                "Cannot issue {$qty} units; only {$lot->quantity} available."
            );
        }

        $lot->update(['quantity' => max(0, (float) $lot->quantity - $qty)]);

        return LotMovement::create([
            'lot_id' => $lot->id,
            'movement_type' => 'issue',
            'quantity' => $qty,
            'reference' => $reference ?: null,
        ]);
    }

    public function getLotsByProduct(int $productId): Collection
    {
        return Lot::where('product_id', $productId)->get();
    }

    public function getExpiringLots(int $daysAhead = 30): Collection
    {
        $today = Carbon::today();
        $cutoff = Carbon::today()->addDays($daysAhead);

        return Lot::whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today, $cutoff])
            ->get();
    }

    public function getAvailableLots(int $productId): Collection
    {
        return Lot::where('product_id', $productId)
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->get();
    }

    public function transferLot(Lot $lot, int $fromWarehouseId, int $toWarehouseId, float $qty): LotMovement
    {
        $lot->update(['warehouse_id' => $toWarehouseId]);

        return LotMovement::create([
            'lot_id' => $lot->id,
            'movement_type' => 'transfer',
            'quantity' => $qty,
            'warehouse_from_id' => $fromWarehouseId,
            'warehouse_to_id' => $toWarehouseId,
        ]);
    }

    public function quarantineLot(Lot $lot, string $reason = ''): void
    {
        $lot->quarantine();

        $lot->movements()->create([
            'movement_type' => 'adjustment',
            'quantity' => 0,
            'notes' => $reason ?: null,
        ]);
    }

    public function getLotStats(int $productId): array
    {
        $lots = Lot::where('product_id', $productId)->get();

        return [
            'total_lots' => $lots->count(),
            'active_lots' => $lots->where('status', 'active')->count(),
            'expired_lots' => $lots->where('status', 'expired')->count(),
            'total_quantity' => (float) $lots->sum('quantity'),
        ];
    }
}
