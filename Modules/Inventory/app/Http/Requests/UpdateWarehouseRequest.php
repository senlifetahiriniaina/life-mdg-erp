<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|unique:inventory_warehouses,code,'.$this->warehouse->id,
            'type' => 'nullable|in:main,transit,virtual',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|size:2',
            'is_active' => 'nullable|boolean',
        ];
    }
}
