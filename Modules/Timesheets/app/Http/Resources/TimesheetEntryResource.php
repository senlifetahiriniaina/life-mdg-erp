<?php

namespace Modules\Timesheets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->full_name,
                'email' => $this->employee->email,
            ]),
            'entry_date' => $this->entry_date->format('Y-m-d'),
            'hours_worked' => $this->hours_worked,
            // Chantier 19 (Lot 2): billable_hours/hourly_rate are real
            // TimesheetEntry columns (used throughout the billing/
            // utilization reports) that this resource never exposed at
            // all — TimeEntries/Index.vue had nowhere to read them from.
            'billable_hours' => $this->billable_hours !== null ? (float) $this->billable_hours : null,
            'billable' => (float) ($this->billable_hours ?? 0) > 0,
            'hourly_rate' => $this->hourly_rate !== null ? (float) $this->hourly_rate : null,
            'billable_amount' => $this->billable_amount,
            'description' => $this->description,
            'status' => $this->status,
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->id,
                'name' => $this->task->name,
            ]),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'submitted_by' => $this->whenLoaded('submitter', fn () => [
                'id' => $this->submitter->id,
                'name' => $this->submitter->name,
            ]),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'approved_by' => $this->whenLoaded('approver', fn () => [
                'id' => $this->approver?->id,
                'name' => $this->approver?->name,
            ]),
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
