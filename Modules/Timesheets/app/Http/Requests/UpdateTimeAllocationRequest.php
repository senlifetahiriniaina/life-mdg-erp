<?php

namespace Modules\Timesheets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => 'sometimes|exists:time_tracking_projects,id',
            // Chantier 32.19: same wrong-table fix as StoreTimeAllocationRequest.
            'cost_center_id' => 'nullable|integer',
            'task_id' => 'nullable|exists:prj_tasks,id',
            'hours' => 'sometimes|numeric|min:0.25',
            'hourly_rate' => 'sometimes|numeric|min:0',
            'is_billable' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'hours.min' => 'Hours must be at least 15 minutes (0.25)',
        ];
    }
}
