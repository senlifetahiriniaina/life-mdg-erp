<?php

declare(strict_types=1);

namespace Modules\BI\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BI\Models\Kpi;

/** @mixin Kpi */
class KpiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => null,
            'value_type' => $this->unit,
            'target_value' => $this->target_value,
            'current_value' => $this->current_value,
            'user_id' => null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
