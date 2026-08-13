<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Compliance First — Input validation for Analytics store operations.
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('analytics.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2048'],
            'status'      => ['nullable', 'string', 'max:64'],
        ];
    }
}
