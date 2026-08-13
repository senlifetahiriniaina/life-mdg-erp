<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CsatSurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'campaign_id' => $this->campaign_id,
            'score' => $this->score,
            'comment' => $this->comment,
            'sent_at' => $this->sent_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),
            'agent_id' => $this->agent_id,
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
