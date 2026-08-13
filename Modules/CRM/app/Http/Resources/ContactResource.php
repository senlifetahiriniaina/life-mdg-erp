<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CRM\Models\Contact;

/** @mixin Contact */
class ContactResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'status' => $this->status,
            'source' => $this->source,
            'account' => $this->whenLoaded('account', fn () => $this->account ? [
                'id' => $this->account->id,
                'name' => $this->account->name,
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            '_links' => [
                'self' => url("/api/v1/crm/contacts/{$this->id}"),
                'opportunities' => url("/api/v1/crm/contacts/{$this->id}/opportunities"),
                'activities' => url("/api/v1/crm/contacts/{$this->id}/activities"),
            ],
        ];
    }
}
