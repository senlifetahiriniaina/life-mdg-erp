<?php

namespace Modules\Timesheets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entry_id' => 'required|exists:timesheet_entries,id',
            'allocations' => 'required|array|min:1',
            'allocations.*.project_id' => 'required|exists:time_tracking_projects,id',
            // Chantier 32.19 (Timesheets deep 14-layer audit): "cost_centers"
            // has never existed as a table anywhere in this app — no
            // CostCenter model/migration exists — so supplying a
            // cost_center_id here was a guaranteed fatal "no such table"
            // QueryException during validation, confirmed empirically, not
            // a hypothetical gap. There is no real cost-center concept in
            // this app to validate against (TimeAllocation::costCenter()'s
            // own stub relation was removed for the same reason — see that
            // model's docblock) — left as a plain, unresolved integer field
            // rather than inventing a new CostCenter subsystem out of scope
            // for this fix.
            'allocations.*.cost_center_id' => 'nullable|integer',
            // Chantier 32.19: "tasks" doesn't exist in this app (the real
            // table is prj_tasks) — the same wrong-table bug already fixed
            // in StoreTimesheetEntryRequest/UpdateTimesheetEntryRequest at
            // Chantier 8.4, missed on this sibling request.
            'allocations.*.task_id' => 'nullable|exists:prj_tasks,id',
            'allocations.*.hours' => 'required|numeric|min:0.25',
            'allocations.*.hourly_rate' => 'required|numeric|min:0',
            'allocations.*.is_billable' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'allocations.required' => 'At least one allocation must be provided',
            'allocations.*.hours.min' => 'Allocation hours must be at least 15 minutes (0.25)',
        ];
    }
}
