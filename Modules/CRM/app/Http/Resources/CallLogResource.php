<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CallLogResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'lead_id' => $this->lead_id,
            'user_id' => $this->user_id,
            'direction' => $this->direction,
            'status' => $this->status,
            'duration_seconds' => $this->duration_seconds,
            'phone_number' => $this->phone_number,
            'recording_url' => $this->recording_url,
            'notes' => $this->notes,
            'called_at' => $this->called_at?->toISOString(),
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'full_name' => trim("{$this->contact->first_name} {$this->contact->last_name}"),
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
