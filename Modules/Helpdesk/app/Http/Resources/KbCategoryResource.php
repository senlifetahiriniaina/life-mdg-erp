<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Helpdesk\Models\KbCategory;

/** @mixin KbCategory */
class KbCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'description' => $this->description,
            'created_at' => $this->created_at->toISOString(),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
                'slug' => $this->parent->slug,
            ] : null),
            'children' => $this->whenLoaded('children', fn () => static::collection($this->children)),
        ];
    }
}
