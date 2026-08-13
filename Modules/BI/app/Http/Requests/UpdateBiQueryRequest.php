<?php

declare(strict_types=1);

namespace Modules\BI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBiQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sql_query' => ['sometimes', 'string'],
            'datasource' => ['nullable', 'string', 'max:100'],
            'result_cache_ttl' => ['nullable', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }
}
