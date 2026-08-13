<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;

/**
 * Advanced attendance service managing:
 * - Biometric device integration and sync
 * - Clock in/out with validation
 * - Exception detection and analysis
 * - Time off request processing
 * - Overtime calculation and reports
 * - Compliance and absenteeism tracking
 */
class AdvancedAttendanceService
{
    /**
     * Biometric device types and configurations
     */
    protected array $biometricDevices = [
        'fingerprint' => [
            'accuracy' => 99.9,
            'enrollment_time_minutes' => 2,
            'verification_time_seconds' => 3,
        ],
        'facial_recognition' => [
            'accuracy' => 98.5,
            'enrollment_time_minutes' => 5,
            'verification_time_seconds' => 2,
        ],
        'iris_scanning' => [
            'accuracy' => 99.99,
            'enrollment_time_minutes' => 3,
            'verification_time_seconds' => 1,
        ],
        'rfid_card' => [
            'accuracy' => 99.99,
            'enrollment_time_minutes' => 0.5,
            'verification_time_seconds' => 1,
        ],
    ];

    /**
     * Attendance exceptions and thresholds
     */
    protected array $exceptionThresholds = [
        'late_arrival_minutes' => 5,
        'early_departure_minutes' => 15,
        'absent_without_notice' => true,
        'consecutive_absences_alert' => 3,
        'monthly_absent_days_alert' => 3,
    ];

    /**
     * Process clock in with biometric validation.
     */
    public function processClockin(
        Employee $employee,
        string $biometricType = 'fingerprint',
        array $biometricData = []
    ): array {
        // Validate employee is active
        if ($employee->status !== 'active') {
            return [
                'success' => false,
                'error' => 'Employee is not in active status',
            ];
        }

        // Check for duplicate clock-in (prevent double clocking)
        $lastClockIn = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('clock_in_time', today())
            ->orderByDesc('clock_in_time')
            ->first();

        if ($lastClockIn && $lastClockIn->clock_out_time === null) {
            return [
                'success' => false,
                'error' => 'Employee already clocked in. Must clock out first.',
                'current_clock_in' => $lastClockIn->clock_in_time,
            ];
        }

        // Verify biometric data
        $biometricVerification = $this->verifyBiometric($employee, $biometricType, $biometricData);
        if (!$biometricVerification['verified']) {
            return [
                'success' => false,
                'error' => 'Biometric verification failed',
                'reason' => $biometricVerification['reason'],
            ];
        }

        $clockinTime = now();
        $scheduledTime = $this->getEmployeeScheduledTime($employee);

        // Detect if late
        $isLate = false;
        $lateMinutes = 0;

        if ($scheduledTime && $clockinTime->isAfter($scheduledTime)) {
            $lateMinutes = $clockinTime->diffInMinutes($scheduledTime);
            $isLate = $lateMinutes > $this->exceptionThresholds['late_arrival_minutes'];
        }

        $attendance = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'clock_in_time' => $clockinTime,
            'scheduled_time' => $scheduledTime,
            'is_late' => $isLate,
            'late_minutes' => $isLate ? $lateMinutes : 0,
            'biometric_type' => $biometricType,
            'biometric_verified' => true,
            'location' => $biometricData['location'] ?? null,
            'device_id' => $biometricData['device_id'] ?? null,
        ]);

        return [
            'success' => true,
            'attendance_record' => $attendance,
            'message' => $isLate ?
                "Clocked in at {$clockinTime->format('H:i')} ({$lateMinutes} minutes late)" :
                "Clocked in at {$clockinTime->format('H:i')}",
            'is_late' => $isLate,
        ];
    }

    /**
     * Process clock out.
     */
    public function processClockout(
        Employee $employee,
        string $biometricType = 'fingerprint',
        array $biometricData = []
    ): array {
        // Find the active clock-in record
        $attendance = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('clock_in_time', today())
            ->whereNull('clock_out_time')
            ->latest('clock_in_time')
            ->first();

        if (!$attendance) {
            return [
                'success' => false,
                'error' => 'No active clock-in record found',
            ];
        }

        $clockoutTime = now();
        $scheduledEndTime = $this->getEmployeeScheduledEndTime($employee);

        // Detect early departure
        $isEarlyDeparture = false;
        $earlyMinutes = 0;

        if ($scheduledEndTime && $clockoutTime->isBefore($scheduledEndTime)) {
            $earlyMinutes = $scheduledEndTime->diffInMinutes($clockoutTime);
            $isEarlyDeparture = $earlyMinutes > $this->exceptionThresholds['early_departure_minutes'];
        }

        // Calculate worked hours
        $workedHours = $attendance->clock_in_time->diffInHours($clockoutTime, absolute: true);
        $workedMinutes = $attendance->clock_in_time->diffInMinutes($clockoutTime, absolute: true) % 60;

        // Update attendance record
        $attendance->update([
            'clock_out_time' => $clockoutTime,
            'scheduled_end_time' => $scheduledEndTime,
            'is_early_departure' => $isEarlyDeparture,
            'early_departure_minutes' => $isEarlyDeparture ? $earlyMinutes : 0,
            'worked_hours' => $workedHours,
            'worked_minutes' => $workedMinutes,
            'biometric_verified_out' => true,
        ]);

        // Calculate overtime if applicable
        $overtime = $this->calculateDailyOvertime($attendance);

        return [
            'success' => true,
            'attendance_record' => $attendance,
            'worked_time' => [
                'hours' => $workedHours,
                'minutes' => $workedMinutes,
                'formatted' => "{$workedHours}h {$workedMinutes}m",
            ],
            'overtime_hours' => $overtime,
            'is_early_departure' => $isEarlyDeparture,
            'message' => "Clocked out at {$clockoutTime->format('H:i')}",
        ];
    }

    /**
     * Detect and flag attendance exceptions.
     */
    public function detectExceptions(Employee $employee, Carbon $date): array {
        $exceptions = [];

        // Check for absent without notice
        $attendanceRecords = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('clock_in_time', $date)
            ->count();

        if ($attendanceRecords === 0) {
            // Check if there's an approved leave request
            $leaveRequest = LeaveRequest::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereDate('leave_start_date', '<=', $date)
                ->whereDate('leave_end_date', '>=', $date)
                ->first();

            if (!$leaveRequest) {
                $exceptions[] = [
                    'type' => 'absent_without_notice',
                    'severity' => 'high',
                    'date' => $date,
                    'message' => 'Employee absent with no approved leave or clock-in record',
                ];
            }
        }

        // Check for late arrivals
        $lastClockin = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('clock_in_time', $date)
            ->first();

        if ($lastClockin && $lastClockin->is_late) {
            $exceptions[] = [
                'type' => 'late_arrival',
                'severity' => 'medium',
                'date' => $date,
                'minutes_late' => $lastClockin->late_minutes,
                'time' => $lastClockin->clock_in_time->format('H:i'),
            ];
        }

        // Check for early departures
        if ($lastClockin && $lastClockin->is_early_departure) {
            $exceptions[] = [
                'type' => 'early_departure',
                'severity' => 'low',
                'date' => $date,
                'minutes_early' => $lastClockin->early_departure_minutes,
            ];
        }

        // Check for short shifts
        if ($lastClockin && $lastClockin->worked_hours < 7) {
            $exceptions[] = [
                'type' => 'short_shift',
                'severity' => 'medium',
                'date' => $date,
                'worked_hours' => $lastClockin->worked_hours,
            ];
        }

        return [
            'employee_id' => $employee->id,
            'date' => $date,
            'has_exceptions' => count($exceptions) > 0,
            'exception_count' => count($exceptions),
            'exceptions' => $exceptions,
        ];
    }

    /**
     * Process time off (leave) request.
     */
    public function processLeaveRequest(
        Employee $employee,
        array $leaveData
    ): array {
        // Validate dates
        $startDate = Carbon::parse($leaveData['leave_start_date']);
        $endDate = Carbon::parse($leaveData['leave_end_date']);

        if ($startDate->isAfter($endDate)) {
            return [
                'success' => false,
                'error' => 'Start date must be before or equal to end date',
            ];
        }

        // Check leave balance
        $leaveBalance = $this->getEmployeeLeaveBalance($employee, $leaveData['leave_type']);

        $requestedDays = $startDate->diffInDays($endDate) + 1; // Include both start and end dates

        if ($leaveBalance < $requestedDays) {
            return [
                'success' => false,
                'error' => "Insufficient leave balance. Available: {$leaveBalance}, Requested: {$requestedDays}",
            ];
        }

        // Create leave request
        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => $leaveData['leave_type'],
            'leave_start_date' => $startDate,
            'leave_end_date' => $endDate,
            'duration_days' => $requestedDays,
            'reason' => $leaveData['reason'] ?? null,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return [
            'success' => true,
            'leave_request' => $leaveRequest,
            'message' => "Leave request submitted for {$requestedDays} days",
        ];
    }

    /**
     * Approve leave request.
     */
    public function approveLeaveRequest(
        LeaveRequest $leaveRequest,
        string $approvedBy
    ): array {
        $leaveRequest->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);

        // Deduct from leave balance
        $this->deductLeaveBalance($leaveRequest->employee_id, $leaveRequest->leave_type, $leaveRequest->duration_days);

        return [
            'success' => true,
            'leave_request' => $leaveRequest,
            'message' => 'Leave request approved',
        ];
    }

    /**
     * Shift verification and override handling.
     */
    public function verifyShift(
        Employee $employee,
        Carbon $date
    ): array {
        // Get employee's scheduled shift
        $scheduledTime = $this->getEmployeeScheduledTime($employee);
        $scheduledEndTime = $this->getEmployeeScheduledEndTime($employee);

        // Get actual attendance
        $attendance = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('clock_in_time', $date)
            ->first();

        if (!$attendance) {
            return [
                'verified' => false,
                'reason' => 'No attendance record found',
            ];
        }

        $verification = [
            'verified' => true,
            'employee_id' => $employee->id,
            'date' => $date,
            'scheduled_start' => $scheduledTime,
            'actual_start' => $attendance->clock_in_time,
            'scheduled_end' => $scheduledEndTime,
            'actual_end' => $attendance->clock_out_time,
            'discrepancies' => [],
        ];

        // Check for discrepancies
        if ($attendance->is_late) {
            $verification['discrepancies'][] = [
                'type' => 'late_arrival',
                'minutes' => $attendance->late_minutes,
            ];
        }

        if ($attendance->is_early_departure) {
            $verification['discrepancies'][] = [
                'type' => 'early_departure',
                'minutes' => $attendance->early_departure_minutes,
            ];
        }

        return $verification;
    }

    /**
     * Generate attendance report (daily, weekly, monthly).
     */
    public function generateAttendanceReport(
        Employee $employee,
        string $period = 'monthly',
        Carbon $dateStart = null,
        Carbon $dateEnd = null
    ): array {
        if (!$dateStart) {
            $dateStart = match ($period) {
                'daily' => today(),
                'weekly' => today()->startOfWeek(),
                'monthly' => today()->startOfMonth(),
                default => today()->startOfMonth(),
            };
        }

        if (!$dateEnd) {
            $dateEnd = match ($period) {
                'daily' => today(),
                'weekly' => today()->endOfWeek(),
                'monthly' => today()->endOfMonth(),
                default => today()->endOfMonth(),
            };
        }

        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('clock_in_time', [$dateStart, $dateEnd])
            ->get();

        $report = [
            'employee_id' => $employee->id,
            'employee_name' => $employee->getFullName(),
            'period' => $period,
            'date_range' => [
                'start' => $dateStart,
                'end' => $dateEnd,
            ],
            'total_days_in_period' => $dateStart->diffInDays($dateEnd) + 1,
            'total_working_days' => $this->countWorkingDays($dateStart, $dateEnd),
            'days_present' => $records->count(),
            'days_absent' => 0,
            'days_on_leave' => 0,
            'late_arrivals' => 0,
            'early_departures' => 0,
            'total_hours_worked' => 0,
            'total_overtime_hours' => 0,
            'average_daily_hours' => 0,
            'daily_records' => [],
        ];

        // Process records
        foreach ($records as $record) {
            $report['daily_records'][] = [
                'date' => $record->clock_in_time->toDateString(),
                'clock_in' => $record->clock_in_time->format('H:i'),
                'clock_out' => $record->clock_out_time?->format('H:i'),
                'hours_worked' => $record->worked_hours + ($record->worked_minutes / 60),
                'is_late' => $record->is_late,
                'late_minutes' => $record->late_minutes,
                'is_early_departure' => $record->is_early_departure,
            ];

            if ($record->is_late) $report['late_arrivals']++;
            if ($record->is_early_departure) $report['early_departures']++;

            $report['total_hours_worked'] += $record->worked_hours + ($record->worked_minutes / 60);
            $report['total_overtime_hours'] += $this->calculateDailyOvertime($record);
        }

        $report['days_absent'] = $report['total_working_days'] - $report['days_present'];
        $report['average_daily_hours'] = $report['days_present'] > 0 ?
            round($report['total_hours_worked'] / $report['days_present'], 2) :
            0;

        return $report;
    }

    /**
     * Analyze absenteeism and trends.
     */
    public function analyzeAbsenteeism(
        Employee $employee,
        int $monthsToAnalyze = 12
    ): array {
        $startDate = now()->subMonths($monthsToAnalyze)->startOfMonth();
        $endDate = now()->endOfMonth();

        $absences = [];
        $trends = [
            'highest_absence_month' => null,
            'consecutive_absences_count' => 0,
            'pattern_analysis' => [],
        ];

        // Analyze by month
        for ($i = 0; $i < $monthsToAnalyze; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $workingDays = $this->countWorkingDays($monthStart, $monthEnd);
            $presentDays = AttendanceRecord::where('employee_id', $employee->id)
                ->whereBetween('clock_in_time', [$monthStart, $monthEnd])
                ->distinct('clock_in_time')
                ->count();

            $absentDays = $workingDays - $presentDays;

            $absences[] = [
                'month' => $monthStart->format('Y-m'),
                'working_days' => $workingDays,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'attendance_percentage' => round(($presentDays / $workingDays) * 100, 2),
            ];
        }

        $totalAbsentDays = array_sum(array_column($absences, 'absent_days'));
        $highestMonth = max($absences, fn ($item) => $item['absent_days']);

        return [
            'employee_id' => $employee->id,
            'analysis_period_months' => $monthsToAnalyze,
            'total_absent_days' => $totalAbsentDays,
            'average_absent_days_per_month' => round($totalAbsentDays / $monthsToAnalyze, 2),
            'highest_absence_month' => $highestMonth['month'],
            'highest_absence_count' => $highestMonth['absent_days'],
            'monthly_breakdown' => $absences,
            'alerts' => $this->generateAbsenteeismAlerts($absences, $employee),
        ];
    }

    /**
     * Calculate overtime from attendance records.
     */
    public function calculateOvertimeReport(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {
        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('clock_in_time', [$periodStart, $periodEnd])
            ->get();

        $report = [
            'employee_id' => $employee->id,
            'period' => [
                'start' => $periodStart,
                'end' => $periodEnd,
            ],
            'daily_overtime' => [],
            'total_overtime_hours' => 0,
            'overtime_rate' => 1.5, // 1.5x pay
            'overtime_cost' => 0,
        ];

        $hourlyRate = $this->getEmployeeHourlyRate($employee);

        foreach ($records as $record) {
            $overtime = $this->calculateDailyOvertime($record);

            if ($overtime > 0) {
                $report['daily_overtime'][] = [
                    'date' => $record->clock_in_time->toDateString(),
                    'overtime_hours' => $overtime,
                    'cost' => round($overtime * $hourlyRate * 1.5, 2),
                ];

                $report['total_overtime_hours'] += $overtime;
                $report['overtime_cost'] += round($overtime * $hourlyRate * 1.5, 2);
            }
        }

        return $report;
    }

    /**
     * Protected helper: Verify biometric data.
     */
    protected function verifyBiometric(
        Employee $employee,
        string $biometricType,
        array $biometricData
    ): array {
        // Validate device exists
        if (!isset($this->biometricDevices[$biometricType])) {
            return [
                'verified' => false,
                'reason' => 'Invalid biometric device type',
            ];
        }

        // Check if employee is enrolled
        $isEnrolled = true; // Placeholder - would check enrollment DB

        if (!$isEnrolled) {
            return [
                'verified' => false,
                'reason' => 'Employee not enrolled in biometric system',
            ];
        }

        // Verify biometric match
        $isMatched = true; // Placeholder - would verify actual biometric match

        return [
            'verified' => $isMatched,
            'reason' => $isMatched ? null : 'Biometric data does not match',
        ];
    }

    /**
     * Protected helper: Get employee scheduled time.
     */
    protected function getEmployeeScheduledTime(Employee $employee): ?Carbon {
        // Placeholder - would get from schedule/shift table
        return Carbon::parse('09:00:00');
    }

    /**
     * Protected helper: Get employee scheduled end time.
     */
    protected function getEmployeeScheduledEndTime(Employee $employee): ?Carbon {
        // Placeholder - would get from schedule/shift table
        return Carbon::parse('17:00:00');
    }

    /**
     * Protected helper: Get employee leave balance.
     */
    protected function getEmployeeLeaveBalance(Employee $employee, string $leaveType): int {
        // Placeholder - would get from leave balance table
        return 20; // 20 days by default
    }

    /**
     * Protected helper: Deduct leave balance.
     */
    protected function deductLeaveBalance(int $employeeId, string $leaveType, int $days): void {
        // Placeholder - would update leave balance table
    }

    /**
     * Protected helper: Calculate daily overtime.
     */
    protected function calculateDailyOvertime(AttendanceRecord $record): float {
        $standardHours = 8;
        $totalHours = $record->worked_hours + ($record->worked_minutes / 60);

        return max(0, $totalHours - $standardHours);
    }

    /**
     * Protected helper: Count working days.
     */
    protected function countWorkingDays(Carbon $start, Carbon $end): int {
        $count = 0;

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            // Skip weekends
            if ($date->isWeekday()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Protected helper: Get employee hourly rate.
     */
    protected function getEmployeeHourlyRate(Employee $employee): float {
        // Placeholder - would calculate from salary
        return 50.0; // $50/hour default
    }

    /**
     * Protected helper: Generate absenteeism alerts.
     */
    protected function generateAbsenteeismAlerts(array $absences, Employee $employee): array {
        $alerts = [];

        $recentAbsences = array_slice($absences, -3); // Last 3 months
        $avgRecentAbsences = array_sum(array_column($recentAbsences, 'absent_days')) / count($recentAbsences);

        if ($avgRecentAbsences >= 3) {
            $alerts[] = [
                'type' => 'high_absenteeism',
                'severity' => 'high',
                'message' => "Employee has {$avgRecentAbsences} average absent days in last 3 months",
            ];
        }

        // Check for consecutive absences
        $consecutiveCount = 0;
        foreach ($absences as $month) {
            if ($month['absent_days'] > 2) {
                $consecutiveCount++;
            } else {
                $consecutiveCount = 0;
            }

            if ($consecutiveCount >= 2) {
                $alerts[] = [
                    'type' => 'consecutive_absent_months',
                    'severity' => 'medium',
                    'message' => 'Multiple consecutive months with high absences',
                ];
                break;
            }
        }

        return $alerts;
    }
}
