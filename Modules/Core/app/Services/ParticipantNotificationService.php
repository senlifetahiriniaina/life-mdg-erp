<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Models\User;
use App\Services\NotificationService;

/**
 * Extends notifications to the app-wide rule requested for Chantier 20:
 * "toute personne intervenue dans un processus, plus la hiérarchie
 * supérieure directe du owner de l'action" — every participant a caller
 * identifies for its own process, plus the action owner's direct manager.
 *
 * Deliberately takes an explicit participant list rather than trying to
 * auto-detect "who touched this record" from the generic audit trail
 * (RecordsActivity/AuditableActions) — a heuristic broad enough to cover
 * every module's own notion of "participant" would produce far more noise
 * than signal. Each caller already knows its own real participants
 * (an ApprovalRequest's requester+approver+history actors, a Ticket's
 * assignee+commenters, etc.) — this service only adds the one generic
 * piece every caller would otherwise have to duplicate: hierarchy
 * resolution and de-duplicated dispatch.
 */
class ParticipantNotificationService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * @param  iterable<User|int|null>  $participants
     */
    public function notifyProcess(
        iterable $participants,
        ?User $owner,
        string $title,
        string $body,
        array $meta = [],
    ): void {
        $users = $this->resolveUsers($participants);

        $superior = $this->resolveHierarchySuperior($owner);
        if ($superior) {
            $users[$superior->id] = $superior;
        }

        foreach ($users as $user) {
            $this->notifications->sendToUser($user, $title, $body, $meta);
        }
    }

    /**
     * The action owner's direct hierarchical superior, resolved through the
     * real HR reporting line (Employee.manager_id → Employee.user_id) —
     * one hop, no new data needed. Returns null whenever the owner has no
     * linked Employee, or that Employee has no manager (top of the chain).
     */
    public function resolveHierarchySuperior(?User $owner): ?User
    {
        if (! $owner) {
            return null;
        }

        return $owner->employee?->manager?->user;
    }

    /**
     * @param  iterable<User|int|null>  $participants
     * @return array<int, User>  keyed by user id, de-duplicated
     */
    private function resolveUsers(iterable $participants): array
    {
        $ids = [];
        $users = [];

        foreach ($participants as $participant) {
            if ($participant instanceof User) {
                $users[$participant->id] = $participant;
            } elseif (is_numeric($participant)) {
                $ids[] = (int) $participant;
            }
        }

        if ($ids !== []) {
            foreach (User::whereIn('id', array_unique($ids))->get() as $user) {
                $users[$user->id] = $user;
            }
        }

        return $users;
    }
}
