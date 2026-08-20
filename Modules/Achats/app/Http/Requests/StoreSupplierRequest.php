<?php

namespace Modules\Achats\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'nullable|unique:achats_suppliers,code',
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            // Chantier 19: Suppliers/Form.vue's field defaults to (and its
            // `type="number"` input sends) a real JSON number — `nullable|
            // string` rejected it (Laravel's `string` rule is `is_string()`,
            // which a decoded JSON int fails), so every real supplier
            // creation/update with the default 30-day value 422'd. The
            // column itself (`achats_suppliers.payment_terms`) is a plain
            // string, so either shape stores fine; no type rule needed.
            'payment_terms' => 'nullable',
            'lead_time_days' => 'nullable|integer|min:1',
        ];
    }
}
