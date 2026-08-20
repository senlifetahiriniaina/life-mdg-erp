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
            // Chantier 8.4: employee_id validated against users.id (a different
            // ID space than the hr_employees.id this column FKs to — the same
            // mismatch fixed in TimesheetEntryPolicy) and task_id against a
            // "tasks" table that doesn't exist in this app at all (the real
            // table is prj_tasks) — every request with a task_id fatalled.
            // Chantier 19 (Lot 2): employee_id downgraded from `required` to
            // `sometimes` — the real create form has no way to know its own
            // caller's hr_employees.id, and the controller now defaults it
            // from the authenticated user's linked employee record.
            'employee_id' => 'sometimes|exists:hr_employees,id',
            'entry_date' => 'required|date|before_or_equal:today',
            'hours_worked' => 'required|numeric|min:0.25|max:24',
            'description' => 'required|string|min:5|max:500',
            'task_id' => 'nullable|exists:prj_tasks,id',
            // Chantier 19 (Lot 2): project_id/billable/hourly_rate were
            // real TimesheetEntry columns with no validated write path at
            // all — silently dropped on every real create, breaking
            // project-billing reports (always 0 billable_hours) and the
            // "General" fallback in every by-project report grouping.
            'project_id' => 'nullable|integer|exists:prj_projects,id',
            'billable' => 'sometimes|boolean',
            'hourly_rate' => 'nullable|numeric|min:0',
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
