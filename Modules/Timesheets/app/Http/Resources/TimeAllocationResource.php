<?php

namespace Modules\Timesheets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_id' => $this->entry_id,
            'entry' => $this->whenLoaded('entry', fn () => [
                'id' => $this->entry->id,
                'entry_date' => $this->entry->entry_date->format('Y-m-d'),
            ]),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
            ]),
            'cost_center_id' => $this->cost_center_id,
            'cost_center' => $this->whenLoaded('costCenter', fn () => [
                'id' => $this->costCenter->id,
                'name' => $this->costCenter->name,
            ]),
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => [
                'id' => $this->task->id,
                'name' => $this->task->name,
            ]),
            'hours' => $this->hours,
            'hourly_rate' => $this->hourly_rate,
            'cost_amount' => $this->cost_amount,
            'is_billable' => $this->is_billable,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
