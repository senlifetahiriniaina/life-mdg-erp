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
        return $this->isOwnEntry($user, $entry)
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
        return $this->isOwnEntry($user, $entry)
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function delete(User $user, TimesheetEntry $entry): bool
    {
        if ($entry->status !== 'draft') {
            return $user->hasAnyRole(['admin', 'manager']);
        }

        return $this->isOwnEntry($user, $entry)
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function approve(User $user, TimesheetEntry $entry): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'hr-manager']);
    }

    public function submit(User $user, TimesheetEntry $entry): bool
    {
        return $this->isOwnEntry($user, $entry)
            || $user->hasAnyRole(['admin', 'manager']);
    }

    /**
     * Chantier 8.4: was `$user->id === $entry->employee_id` — comparing a
     * users.id against an hr_employees.id, the same ID-space mismatch bug
     * pattern already fixed elsewhere in this app (LeaveRequestPolicy,
     * PayrollPolicy) — an employee could never pass any of the checks
     * above on their own entries.
     */
    private function isOwnEntry(User $user, TimesheetEntry $entry): bool
    {
        return $user->employee?->id === $entry->employee_id;
    }
}
