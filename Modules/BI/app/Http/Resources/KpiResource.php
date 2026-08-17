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
        $trend = is_numeric($this->trend) ? (float) $this->trend : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'value' => $this->value !== null ? (float) $this->value : ($this->current_value !== null ? (float) $this->current_value : 0.0),
            'unit' => $this->unit,
            'target' => $this->target !== null ? (float) $this->target : ($this->target_value !== null ? (float) $this->target_value : null),
            'threshold' => $this->threshold_warning !== null ? (float) $this->threshold_warning : null,
            'trend_percentage' => $trend,
            'category' => $this->category ?? ($this->source_module ? strtolower($this->source_module) : 'general'),
            'format' => 'raw',
            'sparkline' => $this->whenLoaded('history', fn () => $this->history->pluck('value')->map(fn ($v) => (float) $v)->values()->all(), []),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
