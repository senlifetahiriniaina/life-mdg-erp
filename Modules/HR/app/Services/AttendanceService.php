<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\Employee;

class AttendanceService
{
    /**
     * Clock in an employee.
     *
     * @param  array<string, mixed>  $data
     */
    public function clockIn(Employee $employee, array $data = []): AttendanceRecord
    {
        // Close any open record first
        $open = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if ($open !== null) {
            $open->update(['clock_out' => now()]);
        }

        return AttendanceRecord::create(array_merge([
            'employee_id' => $employee->id,
            'clock_in' => now(),
            'type' => 'regular',
            'ip_address' => request()->ip(),
        ], $data));
    }

    /**
     * Clock out an employee.
     */
    public function clockOut(Employee $employee): AttendanceRecord
    {
        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->firstOrFail();

        $record->update(['clock_out' => now()]);

        return $record->fresh();
    }

    /**
     * Calculate total worked hours for an employee in a given month.
     */
    public function calculateMonthlyHours(Employee $employee, int $year, int $month): float
    {
        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereYear('clock_in', $year)
            ->whereMonth('clock_in', $month)
            ->whereNotNull('clock_out')
            ->get();

        return $records->sum(function (AttendanceRecord $record): float {
            $totalMinutes = $record->clock_in->diffInMinutes($record->clock_out);

            return max(0.0, ($totalMinutes - $record->break_minutes) / 60);
        });
    }
}
