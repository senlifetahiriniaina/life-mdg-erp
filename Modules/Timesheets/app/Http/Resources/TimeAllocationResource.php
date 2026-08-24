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
            // Chantier 32.19: TimeAllocation::costCenter() (a placeholder
            // relation pointing cost_center_id at App\Models\User — no
            // CostCenter model/table exists anywhere in this app) has been
            // removed for silently exposing an unrelated user's real name
            // whenever cost_center_id happened to collide with a real
            // users.id (see that relation's own removal docblock) —
            // cost_center_id now stays a plain, unresolved id.
            'cost_center_id' => $this->cost_center_id,
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
