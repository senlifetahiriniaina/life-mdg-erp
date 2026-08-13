<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\Pipeline;

/** @mixin Pipeline */
class PipelineResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'stages' => $this->stages,
            'opportunities_count' => $this->whenLoaded('opportunities', fn () => $this->opportunities->count()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
