<?php

declare(strict_types=1);

namespace Modules\BI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKpiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'metric_type' => ['nullable', 'string', 'in:count,sum,avg,custom'],
            'metric' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:50'],
            'source_module' => ['nullable', 'string'],
            'query_id' => ['nullable', 'exists:bi_queries,id'],
            'value' => ['nullable', 'numeric'],
            'target' => ['nullable', 'numeric'],
            'target_value' => ['nullable', 'numeric'],
            'threshold_warning' => ['nullable', 'numeric'],
            'threshold_critical' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:50'],
            'period' => ['nullable', 'string', 'in:daily,weekly,monthly,yearly'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
