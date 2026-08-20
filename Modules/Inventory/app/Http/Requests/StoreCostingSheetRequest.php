<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Inventory\Models\CostingSheet;

class StoreCostingSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference' => 'nullable|string|max:100|unique:inventory_costing_sheets,reference',
            'name' => 'required|string|max:255',
            'product_template_id' => 'nullable|integer|exists:inventory_product_templates,id',
            'opportunity_id' => 'nullable|integer|exists:crm_opportunities,id',
            'gender' => 'nullable|string|max:30',
            'size_range' => 'nullable|string|max:50',
            'season' => 'nullable|string|max:30',
            'quantity' => 'required|integer|min:1',
            'base_currency' => 'required|string|size:3',
            'status' => 'nullable|string|in:' . implode(',', CostingSheet::STATUSES),
            'production_minutes' => 'nullable|numeric|min:0',
            'minute_cost' => 'nullable|numeric|min:0',
            'fixed_cost_coefficient' => 'nullable|numeric|min:0',
            'target_margin_percent' => 'nullable|numeric|min:0|max:1000',

            'lines' => 'nullable|array',
            'lines.*.section' => 'required_with:lines|string|in:' . implode(',', array_keys(CostingSheet::SECTIONS)),
            'lines.*.designation' => 'required_with:lines|string|max:255',
            'lines.*.product_template_id' => 'nullable|integer|exists:inventory_product_templates,id',
            'lines.*.supplier_id' => 'nullable|integer|exists:achats_suppliers,id',
            'lines.*.consumption_qty' => 'nullable|numeric|min:0',
            'lines.*.unit' => 'nullable|string|max:20',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.currency' => 'nullable|string|size:3',
            'lines.*.customs_freight_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.margin_percent' => 'nullable|numeric|min:0|max:1000',
            'lines.*.sequence' => 'nullable|integer|min:0',
        ];
    }
}
