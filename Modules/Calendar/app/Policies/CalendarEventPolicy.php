<?php

declare(strict_types=1);

namespace Modules\Calendar\Policies;

use App\Models\User;
use Modules\Calendar\Models\CalendarEvent;

class CalendarEventPolicy
{
    /**
     * Chantier 19 Lot 3: view()/viewAny() were unconditionally `true` for
     * any authenticated user with Calendar-module route access (i.e. every
     * employee of every company, since this module's route gate is
     * `module:Calendar`+`role:employee,manager,admin` with no per-tenant
     * check of its own) — confirmed via a real HTTP request as a
     * different-company user that this was a live, exploitable
     * cross-tenant leak: any event's full detail (title, description,
     * location) and attendee list (private emails) was readable by id,
     * cross-company, with zero ownership check. Fixed to require the
     * viewer be in the same company as the event, matching this session's
     * established company_id-scoping pattern (CalendarService::
     * createEvent()'s docblock has the full investigation of why
     * `tenant_id` was never actually populated before this). `sameCompany()`
     * falls back to the event's real creator's `company_id` for any
     * pre-fix row whose own `tenant_id` is still null.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        if ((int) $calendarEvent->created_by === $user->id) {
            return true;
        }

        return $this->sameCompany($user, $calendarEvent);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        return (int) $calendarEvent->created_by === $user->id;
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        return (int) $calendarEvent->created_by === $user->id;
    }

    public function restore(User $user, CalendarEvent $calendarEvent): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function forceDelete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $user->hasRole('super-admin');
    }

    private function sameCompany(User $user, CalendarEvent $calendarEvent): bool
    {
        $eventCompanyId = $calendarEvent->tenant_id
            ?? $calendarEvent->createdBy?->company_id;

        if ($eventCompanyId === null || $user->company_id === null) {
            return false;
        }

        return (int) $eventCompanyId === (int) $user->company_id;
    }
}
