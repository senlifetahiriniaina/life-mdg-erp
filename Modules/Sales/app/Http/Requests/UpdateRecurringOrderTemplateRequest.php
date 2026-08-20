<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Sales\Models\RecurringOrderTemplate;

class UpdateRecurringOrderTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'contact_id' => 'nullable|integer',
            'account_id' => 'nullable|integer',
            'currency' => 'nullable|string|size:3',
            'recurrence' => 'sometimes|string|in:' . implode(',', RecurringOrderTemplate::RECURRENCES),
            'next_run_at' => 'sometimes|date',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',

            'lines' => 'nullable|array|min:1',
            'lines.*.product_id' => 'nullable|exists:inventory_products,id',
            'lines.*.description' => 'required_with:lines|string',
            'lines.*.quantity' => 'required_with:lines|numeric|min:0.01',
            'lines.*.unit_price' => 'required_with:lines|numeric|min:0',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
        ];
    }
}
