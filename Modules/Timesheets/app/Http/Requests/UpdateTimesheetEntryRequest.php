<?php

namespace Modules\Timesheets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimesheetEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours_worked' => 'sometimes|numeric|min:0.25|max:24',
            'description' => 'sometimes|string|min:5|max:500',
            // Chantier 8.4: "tasks" table doesn't exist in this app — real
            // table is prj_tasks (same fix as StoreTimesheetEntryRequest).
            'task_id' => 'nullable|exists:prj_tasks,id',
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
