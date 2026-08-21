<?php

namespace Modules\Validation\Listeners;

use Modules\Core\Services\ParticipantNotificationService;
use Modules\Validation\Events\ApprovalApproved;
use Modules\Validation\Events\ApprovalCompleted;
use Modules\Validation\Events\ApprovalRejected;
use Modules\Validation\Events\ApprovalRequestCreated;

/**
 * First-wave wiring for the Chantier 20 notification-extension rule: notify
 * every real participant in the process plus the direct hierarchical
 * superior of whoever owns the action that just happened.
 *
 * Chantier 32.7: every `action_url` below pointed at
 * "/validation/approval-requests/{id}" — a URL that has never existed.
 * Modules\Validation\Providers\RouteServiceProvider registers
 * routes/web.php with NO prefix at all (unlike the API group, which is
 * prefixed api/v1/validation), so the real, routed page is
 * "/approval-requests/{id}" (confirmed via `php artisan route:list`).
 * NotificationBell.vue/Notifications/Index.vue both `router.visit(
 * notif.meta.action_url)` on click — every approval-related notification's
 * "view" link has 404'd since Chantier 20 shipped this listener. Fixed to
 * the real route.
 */
class NotifyApprovalParticipants
{
    public function __construct(private readonly ParticipantNotificationService $notifier)
    {
    }

    public function handleRequestCreated(ApprovalRequestCreated $event): void
    {
        $request = $event->request;
        $approver = $request->approver_id ? \App\Models\User::find($request->approver_id) : null;

        $this->notifier->notifyProcess(
            array_filter([$approver]),
            $request->requester,
            'Nouvelle demande d\'approbation',
            sprintf(
                'Une demande sur "%s" (niveau %d/%d) attend votre validation.',
                $request->workflow->name ?? 'un workflow',
                $request->current_level ?? 1,
                $request->total_levels ?? 1,
            ),
            ['type' => 'warning', 'action_url' => "/approval-requests/{$request->id}"],
        );
    }

    public function handleApproved(ApprovalApproved $event): void
    {
        $request = $event->request;

        $this->notifier->notifyProcess(
            array_merge([$request->requester], $this->historyActors($request)),
            $event->approver,
            'Demande approuvée',
            sprintf('Votre demande a été approuvée par %s.', $event->approver->name),
            ['type' => 'success', 'action_url' => "/approval-requests/{$request->id}"],
        );
    }

    public function handleRejected(ApprovalRejected $event): void
    {
        $request = $event->request;

        $this->notifier->notifyProcess(
            array_merge([$request->requester], $this->historyActors($request)),
            $event->rejectedBy,
            'Demande rejetée',
            sprintf('Votre demande a été rejetée par %s : %s', $event->rejectedBy->name, $event->reason),
            ['type' => 'error', 'action_url' => "/approval-requests/{$request->id}"],
        );
    }

    public function handleCompleted(ApprovalCompleted $event): void
    {
        $request = $event->request;

        $this->notifier->notifyProcess(
            $this->historyActors($request),
            $request->requester,
            $event->approved ? 'Processus d\'approbation terminé' : 'Processus d\'approbation clôturé (rejeté)',
            $event->approved
                ? 'Toutes les étapes d\'approbation ont été validées.'
                : 'Le processus d\'approbation a été clôturé sans validation complète.',
            ['type' => $event->approved ? 'success' : 'error', 'action_url' => "/approval-requests/{$request->id}"],
        );
    }

    /**
     * @return array<int, \App\Models\User>
     */
    private function historyActors($request): array
    {
        return $request->history
            ->pluck('changed_by')
            ->filter()
            ->unique()
            ->map(fn ($id) => \App\Models\User::find($id))
            ->filter()
            ->all();
    }
}
