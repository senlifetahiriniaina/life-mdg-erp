<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Helpdesk\Models\KbArticle;

/** @mixin KbArticle */
class KbArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'status' => $this->status,
            'views' => $this->view_count,
            'helpful' => $this->helpful_count,
            'not_helpful' => $this->not_helpful_count,
            'tags' => $this->tags,
            'created_by' => $this->created_by,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'excerpt' => $this->excerpt
                ?? mb_substr(strip_tags((string) $this->content), 0, 200),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
        ];
    }
}
