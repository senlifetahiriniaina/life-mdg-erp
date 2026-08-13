<?php

namespace Modules\Timesheets\Policies;

use App\Models\User;
use Modules\Timesheets\Models\TimesheetEntry;

class TimesheetEntryPolicy
{
    public function viewAny(User $user): bool
    {
        // Any authenticated user can view (filtered by ownership in controller)
        return true;
    }

    public function view(User $user, TimesheetEntry $entry): bool
    {
        return $user->id === $entry->employee_id
            || $user->hasAnyRole(['admin', 'manager', 'hr-manager']);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TimesheetEntry $entry): bool
    {
        if (in_array($entry->status, ['submitted', 'approved'])) {
            return $user->hasAnyRole(['admin', 'manager']);
        }
        return $user->id === $entry->employee_id
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, TimesheetEntry $entry): bool
    {
        if ($entry->status !== 'draft') {
            return $user->hasAnyRole(['admin', 'manager']);
        }

        return $user->id === $entry->employee_id
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function approve(User $user, TimesheetEntry $entry): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'hr-manager']);
    }

    public function submit(User $user, TimesheetEntry $entry): bool
    {
        return $user->id === $entry->employee_id
            || $user->hasAnyRole(['admin', 'manager']);
    }
}
