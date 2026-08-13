<?php

declare(strict_types=1);

namespace Modules\HR\Policies;

use App\Models\User;
use Modules\HR\Models\AttendanceException;
use Modules\HR\Models\TimeOffRequest;

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
               in_array($exception->status, ['flagged', 'acknowledged']);
    }

    public function approveException(User $user, AttendanceException $exception): bool
    {
        return $user->can('hr.attendance.approve-exception') &&
               $exception->status === 'flagged';
    }

    public function requestTimeOff(User $user): bool
    {
        return $user->can('hr.attendance.request-time-off');
    }

    public function approveTimeOff(User $user, TimeOffRequest $request): bool
    {
        return $user->can('hr.attendance.approve-time-off') &&
               $request->isPending();
    }

    public function rejectTimeOff(User $user, TimeOffRequest $request): bool
    {
        return $user->can('hr.attendance.reject-time-off') &&
               $request->isPending();
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
}
