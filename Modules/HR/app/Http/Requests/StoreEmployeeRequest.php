<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => 'required|string|unique:hr_employees,employee_number',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:hr_employees,email',
            'phone' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string',
            'national_id' => 'nullable|string',
            'passport_number' => 'nullable|string',
            'address' => 'nullable|string',
            'department_id' => 'required|exists:hr_departments,id',
            'job_position_id' => 'required|exists:hr_job_positions,id',
            'hire_date' => 'required|date',
            'probation_end_date' => 'nullable|date|after:hire_date',
            'termination_date' => 'nullable|date',
            'employment_type' => 'nullable|in:full_time,part_time,contract,temporary',
            'manager_id' => 'nullable|exists:hr_employees,id',
            'user_id' => 'nullable|exists:users,id',
        ];
    }
}
