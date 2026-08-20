<?php

declare(strict_types=1);

namespace Modules\Timesheets\Policies;

use App\Models\User;
use Modules\Timesheets\Models\TimesheetPeriod;

/**
 * Chantier 19 (Lot 2 re-verification): TimesheetAdvancedController's
 * "sheets" endpoints (sheetsIndex/storeSheet/updateSheet/submitSheet/
 * approvePeriod/rejectPeriod) had zero `authorize()` calls anywhere, and no
 * Policy class existed for TimesheetPeriod at all — the module's only
 * Policy (TimesheetEntryPolicy) covers individual daily entries, not
 * weekly period submissions. Confirmed empirically over a real HTTP
 * request: any authenticated "employee"-role user (this app's broad
 * by-design role, included in the route-level
 * `role:employee,manager,admin` gate) could approve or reject ANY other
 * employee's submitted weekly timesheet, and could update/submit any other
 * employee's draft sheet — approvePeriod()/rejectPeriod() had no role
 * check whatsoever beyond that outer route gate. Same bug pattern already
 * fixed for LeaveRequestController::approve()/reject() and PayrollPolicy
 * elsewhere in this app (see CLAUDE.md's Chantier 8.3 entries).
 */
class TimesheetPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        // Any authenticated Timesheets user can list — filtered to their
        // own periods in the controller for non-managers, the same
        // pattern already used by TimesheetEntryController::index().
        return true;
    }

    public function view(User $user, TimesheetPeriod $period): bool
    {
        return $this->isOwnPeriod($user, $period)
            || $user->hasAnyRole(['admin', 'manager', 'hr-manager']);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TimesheetPeriod $period): bool
    {
        if ($period->status !== 'draft') {
            return $user->hasAnyRole(['admin', 'manager']);
        }

        return $this->isOwnPeriod($user, $period)
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function submit(User $user, TimesheetPeriod $period): bool
    {
        return $this->isOwnPeriod($user, $period)
            || $user->hasAnyRole(['admin', 'manager']);
    }

    public function approve(User $user, TimesheetPeriod $period): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'hr-manager']);
    }

    /**
     * Compares against the caller's linked hr_employees.id (via
     * User::employee()), not users.id — the same ID-space this app's
     * other timesheet/leave/payroll policies already key ownership
     * checks off.
     */
    private function isOwnPeriod(User $user, TimesheetPeriod $period): bool
    {
        return $user->employee?->id === $period->employee_id;
    }
}
