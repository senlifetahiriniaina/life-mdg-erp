<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Core\Notifications\Channels\ExpoPushChannel;

class NewTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $subject,
        private readonly int $ticketId,
        private readonly string $priority,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['database', ExpoPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'ticket_id' => $this->ticketId,
            'subject' => $this->subject,
            'priority' => $this->priority,
        ];
    }

    /** @return array<string, mixed> */
    public function toExpoPush(mixed $notifiable): array
    {
        return [
            'title' => 'New Ticket: '.$this->subject,
            'body' => 'Priority: '.ucfirst($this->priority),
            'data' => ['screen' => 'helpdesk', 'ticketId' => $this->ticketId],
        ];
    }
}
