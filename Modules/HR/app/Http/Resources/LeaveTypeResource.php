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
            'code' => $this->code,
            'days_per_year' => $this->days_per_year,
            'is_paid' => $this->is_paid,
            'carry_forward' => $this->carry_forward,
            'max_carry_forward_days' => $this->max_carry_forward_days,
            'approval_levels' => $this->approval_levels,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }
}
