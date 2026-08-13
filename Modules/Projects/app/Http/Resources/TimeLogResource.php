<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Projects\Models\TimeLog;

/** @mixin TimeLog */
class TimeLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'hours' => $this->hours,
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'is_billable' => $this->is_billable,
            'hourly_rate' => $this->hourly_rate,
        ];
    }
}
