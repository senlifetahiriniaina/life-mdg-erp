<?php

declare(strict_types=1);

namespace Modules\Calendar\Policies;

use App\Models\User;
use Modules\Calendar\Models\CalendarEvent;

class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return true;
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
}
