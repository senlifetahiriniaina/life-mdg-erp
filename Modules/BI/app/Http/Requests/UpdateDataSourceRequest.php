<?php

declare(strict_types=1);

namespace Modules\BI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            // Chantier 19 Lot 5: same real-connector-registry alignment as
            // StoreDataSourceRequest — see that file's comment.
            'type' => ['sometimes', 'string', 'in:mysql,postgresql,rest_api,csv,google_sheets'],
            'config' => ['sometimes', 'array'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
