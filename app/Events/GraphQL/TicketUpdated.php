<?php

declare(strict_types=1);

namespace App\Events\GraphQL;

use Modules\Helpdesk\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TicketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Ticket $ticket,
        public ?string $oldStatus = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('tickets');
    }

    public function broadcastAs(): string
    {
        return 'ticketUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticket->id,
            'ticket_id' => $this->ticket->id,
            'number' => $this->ticket->number,
            'title' => $this->ticket->title,
            'status' => $this->ticket->status,
            'oldStatus' => $this->oldStatus,
            'updated_at' => $this->ticket->updated_at,
        ];
    }
}
