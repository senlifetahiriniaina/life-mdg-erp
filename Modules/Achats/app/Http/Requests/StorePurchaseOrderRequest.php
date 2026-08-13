<?php

namespace Modules\Achats\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:achats_suppliers,id',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date|after:order_date',
            'currency' => 'nullable|string|size:3',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
