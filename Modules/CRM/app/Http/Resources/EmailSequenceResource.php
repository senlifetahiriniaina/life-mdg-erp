<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailSequenceResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'trigger_event' => $this->trigger_event,
            'created_by' => $this->created_by,
            'steps_count' => $this->steps_count ?? $this->whenLoaded('steps', fn () => $this->steps->count()),
            'enrollments_count' => $this->enrollments_count ?? $this->whenLoaded('enrollments', fn () => $this->enrollments->count()),
            'steps' => $this->whenLoaded('steps'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
