<?php

namespace Modules\Timesheets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimesheetEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:users,id',
            'entry_date' => 'required|date|before_or_equal:today',
            'hours_worked' => 'required|numeric|min:0.25|max:24',
            'description' => 'required|string|min:5|max:500',
            'task_id' => 'nullable|exists:tasks,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'hours_worked.min' => 'Hours worked must be at least 15 minutes (0.25 hours)',
            'hours_worked.max' => 'Hours worked cannot exceed 24 hours per day',
        ];
    }
}
