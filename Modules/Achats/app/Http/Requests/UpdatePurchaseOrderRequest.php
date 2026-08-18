<?php

namespace Modules\Achats\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            // Chantier 10: see StorePurchaseOrderRequest's identical block —
            // same silent-data-loss bug on edit.
            'lines' => 'nullable|array',
            'lines.*.product_id' => 'nullable|exists:inventory_products,id',
            'lines.*.description' => 'required_with:lines|string',
            'lines.*.quantity' => 'required_with:lines|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string',
            'lines.*.unit_price' => 'required_with:lines|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
        ];
    }
}
