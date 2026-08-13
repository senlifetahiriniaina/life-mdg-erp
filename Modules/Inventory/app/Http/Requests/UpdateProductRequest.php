<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => 'nullable|string|unique:inventory_products,sku,'.$this->product->id,
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:inventory_categories,id',
            'unit_id' => 'nullable|exists:inventory_units,id',
            'barcode' => 'nullable|string|unique:inventory_products,barcode,'.$this->product->id,
            'type' => 'nullable|in:storable,consumable,service',
            'cost_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,inactive',
            'currency' => 'nullable|string|size:3',
            'reorder_point' => 'nullable|integer|min:0',
            'reorder_qty' => 'nullable|integer|min:0',
            'valuation_method' => 'nullable|in:average,fifo,fefo',
            'track_serial' => 'nullable|boolean',
            'track_lot' => 'nullable|boolean',
            'image' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'attributes' => 'nullable|json',
        ];
    }
}
