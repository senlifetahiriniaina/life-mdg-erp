<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\ProductTemplate;

class UpdateProductTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $template = $this->route('productTemplate');

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('inventory_product_templates', 'code')->ignore($template)],
            'name' => 'required|string|max:255',
            'family' => 'required|string|in:' . implode(',', array_keys(ProductTemplate::FAMILIES)),
            'category_id' => 'required|integer|exists:inventory_categories,id',
            'unit_id' => 'nullable|integer|exists:inventory_units,id',
            'description' => 'nullable|string|max:2000',
            'default_attributes' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ];
    }
}
