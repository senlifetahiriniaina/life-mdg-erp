<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Inventory\Models\PickingLine;
use Modules\Inventory\Models\PickingOrder;

class WmsService
{
    public function createPickingOrder(array $lines, string $type, int $warehouseId): PickingOrder
    {
        $po = PickingOrder::create([
            'reference' => 'PICK-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'status' => 'pending',
            'source_type' => 'manual',
        ]);

        foreach ($lines as $line) {
            $po->lines()->create([
                'product_id' => $line['product_id'],
                'location_id' => $line['location_id'],
                'quantity_requested' => $line['quantity_requested'],
                'quantity_picked' => 0,
                'status' => 'pending',
            ]);
        }

        return $po->load('lines');
    }

    public function assignPicker(PickingOrder $po, User $user): void
    {
        $po->update([
            'assigned_to' => $user->id,
            'status' => $po->status === 'pending' ? 'in_progress' : $po->status,
            'started_at' => $po->started_at ?? now(),
        ]);
    }

    public function recordPick(PickingLine $line, float $qty): void
    {
        $newPicked = min(
            (float) $line->quantity_picked + $qty,
            (float) $line->quantity_requested
        );

        $status = $newPicked >= (float) $line->quantity_requested ? 'done' : 'partial';

        $line->update([
            'quantity_picked' => $newPicked,
            'status' => $status,
        ]);

        // Check if the whole order is complete
        $po = PickingOrder::with('lines')->find($line->picking_order_id);
        if ($po instanceof PickingOrder) {
            $allDone = PickingLine::where('picking_order_id', $po->id)
                ->whereIn('status', ['pending', 'partial'])
                ->doesntExist();
            if ($allDone) {
                $this->completePicking($po);
            }
        }
    }

    public function completePicking(PickingOrder $po): void
    {
        $po->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function getNextPickLine(PickingOrder $po): ?PickingLine
    {
        return PickingLine::with(['product', 'location'])
            ->where('picking_order_id', $po->id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('id')
            ->first();
    }
}
