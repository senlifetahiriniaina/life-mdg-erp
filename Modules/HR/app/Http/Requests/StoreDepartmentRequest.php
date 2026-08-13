<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:hr_departments,name',
            'code' => 'required|string|unique:hr_departments,code',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:hr_employees,id',
            'budget_allocation' => 'nullable|numeric|min:0',
        ];
    }
}
