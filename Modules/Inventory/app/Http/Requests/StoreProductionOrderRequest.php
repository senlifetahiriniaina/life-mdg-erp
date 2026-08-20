<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Inventory\Models\ProductionOrder;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference' => 'nullable|string|max:100|unique:inventory_production_orders,reference',
            'costing_sheet_id' => 'nullable|integer|exists:inventory_costing_sheets,id',
            'sales_order_id' => 'nullable|integer',
            'subcontractor_supplier_id' => 'nullable|integer|exists:achats_suppliers,id',
            'quantity' => 'required|integer|min:1',
            'status' => 'nullable|string|in:' . implode(',', ProductionOrder::STATUSES),
            'expected_delivery_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}
