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
        ];
    }
}
