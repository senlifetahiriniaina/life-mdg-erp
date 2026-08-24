<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Modules\HR\Models\AttendanceException;
use Modules\HR\Models\Employee;
use Modules\HR\Models\TimeOffRequest;

/**
 * Chantier 32: this policy backs 5 different models (BiometricDevice,
 * AttendanceRecord, AttendanceException, TimeOffRequest, ShiftSchedule) via
 * bespoke, non-CRUD ability names — most abilities are viewAny-style
 * (User-only, no per-record model), so real per-record company scoping for
 * those happens at the controller-query level (see AttendanceController/
 * AttendanceBiometricController), not here.
 *
 * The 4 abilities below DO take a specific record and are extended with a
 * same-company check, resolved via the record's own `employee` relation —
 * AttendanceException/TimeOffRequest/ShiftSchedule have no `company_id`
 * column of their own (out of this chantier's 8-table migration scope,
 * which only added one to the tables real controllers directly query), so
 * their tenant boundary is derived through the employee they belong to,
 * mirroring Modules\Projects\Http\Controllers\Api\Concerns\
 * ScopesToProjectCompany's "resolve tenant via parent relation" pattern.
 *
 * BiometricDevice has no `employee`/employee-derivable relation at all (it's
 * a physical device, not owned by any one employee) — its abilities
 * (manageBiometricDevices/verifyRecords/viewAnalytics) are deliberately left
 * as pure permission-string RBAC, since there is no per-record tenant
 * signal to check against without inventing a new scoping mechanism this
 * model was never designed to support.
 */
class AttendancePolicy
{
    public function viewAttendance(User $user): bool
    {
        return $user->can('hr.attendance.view');
    }

    public function viewPersonalAttendance(User $user): bool
    {
        return $user->can('hr.attendance.view-personal');
    }

    public function recordAttendance(User $user): bool
    {
        return $user->can('hr.attendance.record');
    }

    public function manageBiometricDevices(User $user): bool
    {
        return $user->can('hr.attendance.manage-devices');
    }

    public function verifyRecords(User $user): bool
    {
        return $user->can('hr.attendance.verify-records');
    }

    public function handleExceptions(User $user, AttendanceException $exception): bool
    {
        return $user->can('hr.attendance.handle-exceptions') &&
               in_array($exception->status, ['flagged', 'acknowledged']) &&
               $this->sameCompanyViaEmployee($user, $exception->employee);
    }

    public function approveException(User $user, AttendanceException $exception): bool
    {
        return $user->can('hr.attendance.approve-exception') &&
               $exception->status === 'flagged' &&
               $this->sameCompanyViaEmployee($user, $exception->employee);
    }

    public function requestTimeOff(User $user): bool
    {
        return $user->can('hr.attendance.request-time-off');
    }

    public function approveTimeOff(User $user, TimeOffRequest $request): bool
    {
        return $user->can('hr.attendance.approve-time-off') &&
               $request->isPending() &&
               $this->sameCompanyViaEmployee($user, $request->employee);
    }

    public function rejectTimeOff(User $user, TimeOffRequest $request): bool
    {
        return $user->can('hr.attendance.reject-time-off') &&
               $request->isPending() &&
               $this->sameCompanyViaEmployee($user, $request->employee);
    }

    public function manageshifts(User $user): bool
    {
        return $user->can('hr.attendance.manage-shifts');
    }

    public function viewAnalytics(User $user): bool
    {
        return $user->can('hr.attendance.view-analytics');
    }

    public function exportAttendance(User $user): bool
    {
        return $user->can('hr.attendance.export');
    }

    private function sameCompanyViaEmployee(User $user, ?Employee $employee): bool
    {
        return ((int) ($user->company_id ?? 0)) === ((int) ($employee?->company_id ?? 0));
    }
}
