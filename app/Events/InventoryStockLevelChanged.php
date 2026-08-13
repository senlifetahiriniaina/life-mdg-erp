<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Inventory\Models\WarehouseStock;

class InventoryStockLevelChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WarehouseStock $stock,
        public readonly int $oldQuantity,
        public readonly string $reason = 'adjustment'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("inventory.stock.{$this->stock->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return 'stock.level_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'              => $this->stock->id,
            'product_id'      => $this->stock->product_id,
            'warehouse_id'    => $this->stock->warehouse_id,
            'old_quantity'    => $this->oldQuantity,
            'new_quantity'    => $this->stock->quantity,
            'difference'      => $this->stock->quantity - $this->oldQuantity,
            'reason'          => $this->reason,
            'reorder_level'   => $this->stock->reorder_level,
            'is_low_stock'    => $this->stock->quantity <= $this->stock->reorder_level,
            'updated_at'      => $this->stock->updated_at->toIso8601String(),
        ];
    }
}
