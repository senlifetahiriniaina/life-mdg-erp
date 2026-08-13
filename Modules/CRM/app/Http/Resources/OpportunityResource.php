<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\Opportunity;

/** @mixin Opportunity */
class OpportunityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stage' => $this->stage,
            'status' => $this->status,
            'probability' => $this->probability,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'description' => $this->description,
            'pipeline' => $this->whenLoaded('pipeline', fn () => $this->pipeline ? [
                'id' => $this->pipeline->id,
                'name' => $this->pipeline->name,
            ] : null),
            'account' => $this->whenLoaded('account', fn () => $this->account ? [
                'id' => $this->account->id,
                'name' => $this->account->name,
            ] : null),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'full_name' => trim("{$this->contact->first_name} {$this->contact->last_name}"),
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
