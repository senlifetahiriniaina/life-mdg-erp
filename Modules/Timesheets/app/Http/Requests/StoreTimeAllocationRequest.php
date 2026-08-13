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
            'allocations.*.cost_center_id' => 'nullable|exists:cost_centers,id',
            'allocations.*.task_id' => 'nullable|exists:tasks,id',
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
