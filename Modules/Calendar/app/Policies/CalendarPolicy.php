<?php

declare(strict_types=1);

namespace Modules\Calendar\Policies;

use App\Models\User;
use Modules\Calendar\Models\Calendar;

class CalendarPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Calendar $calendar): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Calendar $calendar): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return (int) $calendar->user_id === $user->id;
    }

    public function delete(User $user, Calendar $calendar): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return (int) $calendar->user_id === $user->id;
    }

    public function restore(User $user, Calendar $calendar): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin']);
    }

    public function forceDelete(User $user, Calendar $calendar): bool
    {
        return $user->hasRole('super_admin');
    }
}
