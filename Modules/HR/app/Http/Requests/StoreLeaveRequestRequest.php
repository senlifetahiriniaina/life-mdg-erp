<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'nullable|exists:hr_employees,id',
            'leave_type_id' => 'nullable|exists:hr_leave_types,id',
            'type' => 'required_without:leave_type_id|nullable|string|max:100',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days_requested' => 'nullable|numeric|min:0.5',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Leave end date must be on or after start date',
        ];
    }
}
