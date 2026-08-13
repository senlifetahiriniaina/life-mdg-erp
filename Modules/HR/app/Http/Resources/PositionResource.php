<?php

namespace Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'department_id' => $this->department_id,
            'level' => $this->level,
            'salary_min' => $this->salary_min ? (float) $this->salary_min : null,
            'salary_max' => $this->salary_max ? (float) $this->salary_max : null,
            'headcount' => $this->headcount,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
