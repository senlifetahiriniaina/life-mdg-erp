<?php

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'title' => $this->title,
            'status' => $this->status,
            'total' => (float) $this->total,
            'submitted_at' => $this->submitted_at,
            'approved_at' => $this->approved_at,
            'lines' => $this->whenLoaded('lines', function () {
                return ExpenseLineResource::collection($this->lines);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
