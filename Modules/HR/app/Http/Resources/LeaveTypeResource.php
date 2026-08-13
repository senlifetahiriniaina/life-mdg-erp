<?php

namespace Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'days_per_year' => $this->days_per_year,
            'is_paid' => $this->is_paid,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }
}
