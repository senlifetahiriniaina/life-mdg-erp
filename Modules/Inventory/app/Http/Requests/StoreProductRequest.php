<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|unique:inventory_products,sku',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:inventory_categories,id',
            'unit_id' => 'nullable|exists:inventory_units,id',
            'barcode' => 'nullable|string|unique:inventory_products,barcode',
            'type' => 'nullable|in:storable,consumable,service',
            'cost_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'selling_price' => 'required_without:sale_price|nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'category' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,inactive',
            'reorder_level' => 'nullable|integer|min:0',
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
