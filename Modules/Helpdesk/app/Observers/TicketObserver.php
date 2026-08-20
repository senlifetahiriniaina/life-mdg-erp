<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Observers;

use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\ParticipantNotificationService;
use Modules\Helpdesk\Models\Ticket;

class TicketObserver
{
    public function __construct(private readonly ParticipantNotificationService $notifier)
    {
    }

    public function created(Ticket $ticket): void
    {
        if (! $ticket->assignee_id) {
            return;
        }

        $this->notifier->notifyProcess(
            [$ticket->assignee],
            $ticket->reporter,
            'Nouveau ticket assigné',
            sprintf('Le ticket %s ("%s") vous a été assigné.', $ticket->ticket_number, $ticket->subject),
            ['type' => 'warning', 'action_url' => "/helpdesk/tickets/{$ticket->id}"],
        );
    }

    public function updated(Ticket $ticket): void
    {
        // Ticket::booted()'s static::created() hook calls SlaService::apply(),
        // which itself calls $ticket->update() on this same in-flight instance
        // — a nested save that runs before Eloquent's own finishSave()/
        // syncOriginal() for the outer insert, so wasChanged() spuriously
        // reports every originally-set attribute (including assignee_id/
        // status) as changed on that nested update. getOriginal('id') is
        // still null at that point (syncOriginal() hasn't run yet for the
        // outer insert) even though the row already exists — unlike
        // wasRecentlyCreated, which stays true for this instance's whole
        // lifetime and would wrongly suppress every later legitimate
        // update too, this precisely targets only the nested-save case.
        if ($ticket->exists && is_null($ticket->getOriginal('id'))) {
            return;
        }

        // The owner of a status-change/reassign action is whoever performed
        // it (an agent), not the ticket's reporter — fall back to the
        // assignee when there's no authenticated request context (e.g. a
        // console job) so the notification still has a real owner to
        // resolve a hierarchy superior from.
        $actor = Auth::user() ?? $ticket->assignee;

        if ($ticket->wasChanged('status')) {
            $this->notifier->notifyProcess(
                array_filter([$ticket->reporter, $ticket->assignee], fn ($u) => $u && $actor && $u->id !== $actor->id),
                $actor,
                'Statut du ticket mis à jour',
                sprintf('Le ticket %s est passé au statut "%s".', $ticket->ticket_number, $ticket->status),
                ['type' => 'info', 'action_url' => "/helpdesk/tickets/{$ticket->id}"],
            );
        }

        if ($ticket->wasChanged('assignee_id') && $ticket->assignee_id) {
            $this->notifier->notifyProcess(
                [$ticket->assignee],
                $actor,
                'Ticket réassigné',
                sprintf('Le ticket %s ("%s") vous a été assigné.', $ticket->ticket_number, $ticket->subject),
                ['type' => 'warning', 'action_url' => "/helpdesk/tickets/{$ticket->id}"],
            );
        }
    }
}
