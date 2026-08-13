<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Helpdesk\Models\Ticket;

class TicketStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $oldStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('helpdesk'),
            new Channel("ticket.{$this->ticket->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->ticket->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->ticket->status,
        ];
    }
}
