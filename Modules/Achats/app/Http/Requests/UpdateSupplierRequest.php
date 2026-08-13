<?php

namespace Modules\Achats\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')->id;

        return [
            'code' => "nullable|unique:achats_suppliers,code,{$supplierId}",
            'name' => 'string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'payment_terms' => 'nullable|string',
            'lead_time_days' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }
}
