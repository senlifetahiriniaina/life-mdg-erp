<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\Lead;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'source' => $this->source,
            'score' => $this->score,
            'estimated_value' => $this->estimated_value,
            'currency' => $this->currency,
            'description' => $this->description,
            'converted_at' => $this->converted_at?->toISOString(),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'full_name' => trim("{$this->contact->first_name} {$this->contact->last_name}"),
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
