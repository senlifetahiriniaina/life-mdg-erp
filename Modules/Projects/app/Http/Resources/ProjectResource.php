<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Projects\Models\Project;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => null,
            'owner_id' => $this->owner_id,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->end_date?->toDateString(),
            'budget' => $this->budget,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            '_links' => [
                'self' => url("/api/v1/projects/{$this->id}"),
                'tasks' => url("/api/v1/projects/{$this->id}/tasks"),
                'members' => url("/api/v1/projects/{$this->id}/members"),
                'report' => url("/api/v1/projects/{$this->id}/report"),
            ],
        ];
    }
}
