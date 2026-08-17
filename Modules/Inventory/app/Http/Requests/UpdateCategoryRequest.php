<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|unique:inventory_categories,name,'.$this->category->id,
            'slug' => 'nullable|string|unique:inventory_categories,slug,'.$this->category->id,
            'description' => 'nullable|string',
        ];
    }
}
