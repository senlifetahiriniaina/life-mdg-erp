<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|unique:hr_departments,name,'.$this->department->id,
            'code' => 'nullable|string|unique:hr_departments,code,'.$this->department->id,
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:hr_employees,id',
            'budget_allocation' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
