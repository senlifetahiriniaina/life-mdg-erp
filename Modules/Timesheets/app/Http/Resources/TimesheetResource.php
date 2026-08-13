<?php

namespace Modules\Timesheets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'status' => $this->status,
            'total_hours' => (float) $this->total_hours,
            'billable_hours' => (float) $this->billable_hours,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'entries_count' => $this->entries()->count(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
