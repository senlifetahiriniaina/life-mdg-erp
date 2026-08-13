<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Models\Shipment;

/** @mixin Shipment */
class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'carrier_id' => $this->carrier_id,
            'carrier' => $this->whenLoaded('carrier', fn () => new CarrierResource($this->carrier)),
            'order_id' => $this->order_id,
            'status' => $this->status,
            'tracking_number' => $this->tracking_number,
            'label_url' => $this->label_url,
            'origin_address' => $this->origin_address,
            'destination_address' => $this->destination_address,
            'weight_kg' => $this->weight_kg,
            'dimensions' => $this->dimensions,
            'service_type' => $this->service_type,
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $this->actual_cost,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'estimated_delivery_at' => $this->estimated_delivery_at,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'status' => $e->status,
                'location' => $e->location,
                'description' => $e->description,
                'occurred_at' => $e->occurred_at->toIso8601String(),
            ])),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
