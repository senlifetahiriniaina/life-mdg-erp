<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Models\Unit;

/** @mixin Unit */
class UnitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'type' => $this->type,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
