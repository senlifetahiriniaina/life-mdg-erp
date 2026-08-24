<?php

namespace Modules\Achats\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Chantier 32.13: same cross-tenant false-collision fix as
            // StoreSupplierRequest — see its own docblock.
            'code' => [
                'nullable',
                Rule::unique('achats_suppliers', 'code')->where('company_id', $this->user()?->company_id)->ignore($supplierId),
            ],
            'name' => 'string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            // Chantier 19: same fix as StoreSupplierRequest — Suppliers/Form.vue
            // sends payment_terms as a real JSON number, which `string` rejects.
            'payment_terms' => 'nullable',
            'lead_time_days' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }
}
