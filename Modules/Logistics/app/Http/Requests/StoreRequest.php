<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Compliance First — Input validation for Logistics store operations.
 * Enforces OWASP input validation requirements.
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('logistics.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2048'],
            'status'      => ['nullable', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Le nom est obligatoire.'),
            'name.max'      => __('Le nom ne peut pas dépasser 255 caractères.'),
        ];
    }
}
