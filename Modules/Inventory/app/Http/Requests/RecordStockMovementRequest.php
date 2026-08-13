<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:inventory_products,id',
            'warehouse_id' => 'nullable|exists:inventory_warehouses,id',
            'type' => 'required|in:in,out,adjustment,return,transfer',
            'quantity' => 'required|integer',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'reason' => 'nullable|string',
        ];
    }
}
