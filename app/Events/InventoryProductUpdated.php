<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Inventory\Models\Product;

class InventoryProductUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly string $action = 'updated'
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("inventory.products.{$this->product->tenant_id}");
    }

    public function broadcastAs(): string
    {
        return "product.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->product->id,
            'name'         => $this->product->name,
            'sku'          => $this->product->sku,
            'status'       => $this->product->status,
            'cost_price'   => $this->product->cost_price,
            'selling_price' => $this->product->selling_price,
            'updated_at'   => $this->product->updated_at->toIso8601String(),
        ];
    }
}
