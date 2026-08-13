<?php

namespace Modules\Timesheets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'project_id' => $this->project_id,
            'task_description' => $this->task_description,
            'work_date' => $this->work_date->toDateString(),
            'hours' => (float) $this->hours,
            'billable' => $this->billable,
            'rate' => $this->rate ? (float) $this->rate : null,
            'amount' => $this->rate ? round($this->hours * $this->rate, 2) : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
