<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'costing_sheet_id' => 'nullable|integer|exists:inventory_costing_sheets,id',
            'sales_order_id' => 'nullable|integer',
            'subcontractor_supplier_id' => 'nullable|integer|exists:achats_suppliers,id',
            'quantity' => 'sometimes|integer|min:1',
            'expected_delivery_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}
