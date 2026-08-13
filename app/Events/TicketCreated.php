<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Helpdesk\Models\Ticket;

class TicketCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Ticket $ticket) {}

    public function broadcastOn(): Channel
    {
        return new Channel('helpdesk.tickets');
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->ticket->id,
            'subject'    => $this->ticket->subject,
            'status'     => $this->ticket->status,
            'priority'   => $this->ticket->priority,
            'created_at' => $this->ticket->created_at?->toISOString(),
        ];
    }
}
