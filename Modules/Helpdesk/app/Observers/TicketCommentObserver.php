<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Observers;

use Modules\Core\Services\ParticipantNotificationService;
use Modules\Helpdesk\Models\TicketComment;

class TicketCommentObserver
{
    public function __construct(private readonly ParticipantNotificationService $notifier)
    {
    }

    public function created(TicketComment $comment): void
    {
        $ticket = $comment->ticket;
        if (! $ticket) {
            return;
        }

        $participants = collect([$ticket->reporter, $ticket->assignee])
            ->merge($ticket->comments()->with('user')->get()->pluck('user'))
            ->filter()
            ->reject(fn ($user) => $user->id === $comment->user_id)
            ->unique('id')
            ->values()
            ->all();

        $this->notifier->notifyProcess(
            $participants,
            $comment->user,
            'Nouveau commentaire sur un ticket',
            sprintf('%s a commenté le ticket %s.', $comment->user->name, $ticket->ticket_number),
            ['type' => 'info', 'action_url' => "/helpdesk/tickets/{$ticket->id}"],
        );
    }
}
