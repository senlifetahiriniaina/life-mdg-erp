<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'status' => $this->status, 'priority' => $this->priority, 'created_at' => $this->created_at?->toISOString(), '_links' => ['self' => url("/api/v1/helpdesk/tickets/{$this->id}")]];
    }
}
