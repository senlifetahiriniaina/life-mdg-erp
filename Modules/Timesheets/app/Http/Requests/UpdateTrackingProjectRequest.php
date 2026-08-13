<?php

namespace Modules\Timesheets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrackingProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255|unique:time_tracking_projects,name,'.$this->route('project')->id,
            'code' => 'sometimes|string|max:50|unique:time_tracking_projects,code,'.$this->route('project')->id,
            'description' => 'nullable|string|max:1000',
            'budget_hours' => 'sometimes|numeric|min:1',
            'department_id' => 'nullable|exists:departments,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'status' => 'sometimes|in:active,paused,completed,archived',
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'End date must be on or after the start date',
        ];
    }
}
