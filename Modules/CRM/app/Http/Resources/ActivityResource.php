<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\Activity;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'due_at' => $this->due_at?->toISOString(),
            'done_at' => $this->done_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
