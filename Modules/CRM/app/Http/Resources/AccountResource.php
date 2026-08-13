<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\Account;

/** @mixin Account */
class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'industry' => $this->industry,
            'website' => $this->website,
            'phone' => $this->phone,
            'email' => $this->email,
            'employee_count' => $this->employee_count,
            'annual_revenue' => $this->annual_revenue,
            'currency' => $this->currency,
            'billing_address' => $this->billing_address,
            'billing_city' => $this->billing_city,
            'billing_country' => $this->billing_country,
            'description' => $this->description,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null),
            'contacts_count' => $this->whenLoaded('contacts', fn () => $this->contacts->count()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
