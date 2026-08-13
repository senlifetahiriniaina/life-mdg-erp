<?php

declare(strict_types=1);

namespace Modules\HR\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\HR\Models\JobPosition;

/** @mixin JobPosition */
class JobPositionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'level' => $this->level,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'is_active' => $this->is_active,
            'employees_count' => $this->whenCounted('employees'),
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
