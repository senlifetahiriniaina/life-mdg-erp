<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Inventory\Models\Stock;

class StockUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Stock $stock) {}

    public function broadcastOn(): Channel
    {
        return new Channel('inventory.stock');
    }

    public function broadcastAs(): string
    {
        return 'stock.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id'   => $this->stock->product_id,
            'warehouse_id' => $this->stock->warehouse_id,
            'quantity'     => $this->stock->quantity,
            'avg_cost'     => $this->stock->avg_cost,
        ];
    }
}
