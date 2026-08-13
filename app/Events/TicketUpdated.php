<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Helpdesk\Models\Ticket;

class TicketUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Ticket $ticket) {}

    public function broadcastOn(): Channel
    {
        return new Channel('helpdesk.tickets');
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->ticket->id,
            'subject'     => $this->ticket->subject,
            'status'      => $this->ticket->status,
            'priority'    => $this->ticket->priority,
            'assignee_id' => $this->ticket->assignee_id,
            'sla_breached' => $this->ticket->sla_breached,
            'updated_at'  => $this->ticket->updated_at?->toISOString(),
        ];
    }
}
