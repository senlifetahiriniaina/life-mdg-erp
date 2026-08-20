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
            // Chantier 19 Lot 5: was `in:mysql,postgres,sqlite,api,csv` — a
            // vocabulary that matches none of DataSourceService::resolve()'s
            // real connector registry ('mysql','postgresql','rest_api','csv',
            // 'google_sheets', confirmed via DataSourceService's own
            // supportedTypes()). 'postgres' (not 'postgresql') and 'api' (not
            // 'rest_api') meant the real DataSources/Index.vue create form
            // 422'd on PostgreSQL and silently created an unusable source on
            // "API REST" (would 422 later at test/sync/schema time instead,
            // once DataSourceService::resolve() threw on the unknown type);
            // 'sqlite' has never had any connector at all. Aligned to the
            // real, working connector registry.
            'type' => ['required', 'string', 'in:mysql,postgresql,rest_api,csv,google_sheets'],
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
