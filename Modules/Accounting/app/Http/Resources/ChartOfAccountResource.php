<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Accounting\Models\ChartOfAccount;

/** @mixin ChartOfAccount */
class ChartOfAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'code' => $this->parent->code,
                'name' => $this->parent->name,
            ] : null),
            'children' => $this->whenLoaded('children', fn () => ChartOfAccountResource::collection($this->children)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
