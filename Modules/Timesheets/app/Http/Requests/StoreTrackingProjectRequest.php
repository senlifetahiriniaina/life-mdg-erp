<?php

namespace Modules\Timesheets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackingProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:time_tracking_projects',
            'code' => 'required|string|max:50|unique:time_tracking_projects',
            'description' => 'nullable|string|max:1000',
            'budget_hours' => 'required|numeric|min:1',
            // Chantier 19 (Lot 2): "departments" doesn't exist anywhere in
            // this app's schema (real table is hr_departments) — any
            // caller supplying department_id hit a fatal "no such table"
            // SQL error during validation, not just a 422.
            'department_id' => 'nullable|exists:hr_departments,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'End date must be on or after the start date',
        ];
    }
}
