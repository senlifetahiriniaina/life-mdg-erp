<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'barcode' => $this->barcode,
            'type' => $this->type,
            'category_id' => $this->category_id,
            'category' => $this->category ?? ($this->relationLoaded('category') ? new CategoryResource($this->getRelation('category')) : null),
            'unit_id' => $this->unit_id,
            'unit' => $this->unit,
            'cost_price' => (float) $this->cost_price,
            'sale_price' => (float) $this->sale_price,
            'selling_price' => (float) ($this->selling_price ?? $this->sale_price),
            'status' => $this->status ?? ($this->is_active ? 'active' : 'inactive'),
            'currency' => $this->currency,
            'margin_percent' => $this->getMarginPercent(),
            'reorder_point' => $this->reorder_point,
            'reorder_qty' => $this->reorder_qty,
            'valuation_method' => $this->valuation_method,
            'track_serial' => $this->track_serial,
            'track_lot' => $this->track_lot,
            'image' => $this->image,
            'is_active' => $this->is_active,
            'is_low_stock' => $this->isLowStock(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
