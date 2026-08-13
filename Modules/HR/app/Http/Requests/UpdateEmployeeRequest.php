<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:hr_employees,email,'.$this->employee->id,
            'phone' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string',
            'national_id' => 'nullable|string',
            'passport_number' => 'nullable|string',
            'address' => 'nullable|string',
            'department_id' => 'nullable|exists:hr_departments,id',
            'job_position_id' => 'nullable|exists:hr_job_positions,id',
            'probation_end_date' => 'nullable|date',
            'termination_date' => 'nullable|date',
            'employment_type' => 'nullable|in:full_time,part_time,contract,temporary',
            'manager_id' => 'nullable|exists:hr_employees,id',
            'status' => 'nullable|in:active,on_leave,on_probation,terminated',
            'user_id' => 'nullable|exists:users,id',
        ];
    }
}
