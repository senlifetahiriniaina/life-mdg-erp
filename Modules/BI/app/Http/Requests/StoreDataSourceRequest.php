<?php

declare(strict_types=1);

namespace Modules\BI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:mysql,postgres,sqlite,api,csv'],
            'config' => ['nullable', 'array'],
            'connection_config' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);
        if (is_array($data) && empty($data['config']) && ! empty($data['connection_config'])) {
            $data['config'] = $data['connection_config'];
        }

        return $key !== null ? ($data[$key] ?? $default) : $data;
    }
}
