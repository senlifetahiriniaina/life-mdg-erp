<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;

/**
 * Service for managing employee absences including PTO, sick days, unpaid leave,
 * accrual rules, balance reporting, and approval workflows.
 */
class AbsenceManagementService
{
    const LEAVE_TYPES = [
        'vacation' => 'Paid Time Off',
        'sick' => 'Sick Leave',
        'unpaid' => 'Unpaid Leave',
        'maternity' => 'Maternity Leave',
        'paternity' => 'Paternity Leave',
        'bereavement' => 'Bereavement Leave',
        'jury' => 'Jury Duty',
    ];

    /**
     * Initialize leave balances for an employee.
     */
    public function initializeLeaveBalances(Employee $employee, int $year = null): void
    {
        $year = $year ?? now()->year;

        foreach (self::LEAVE_TYPES as $type => $label) {
            $accrualDays = $this->getAnnualAccrualDays($type, $employee);

            LeaveBalance::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type' => $type,
                    'year' => $year,
                ],
                [
                    'accrual_days_per_year' => $accrualDays,
                    'balance' => $accrualDays,
                    'used' => 0,
                    'pending' => 0,
                    'reset_date' => Carbon::createFromDate($year, 1, 1),
                ]
            );
        }
    }

    /**
     * Get annual accrual days for a leave type.
     * Can be customized per employee or use defaults.
     */
    public function getAnnualAccrualDays(string $leaveType, Employee $employee = null): float
    {
        // Default accrual rules
        $defaults = [
            'vacation' => 20.0,
            'sick' => 10.0,
            'unpaid' => 0.0, // No accrual for unpaid
            'maternity' => 12.0,
            'paternity' => 2.0,
            'bereavement' => 3.0,
            'jury' => 0.0, // As needed
        ];

        return $defaults[$leaveType] ?? 0.0;
    }

    /**
     * Get current leave balance for an employee.
     */
    public function getLeaveBalance(Employee $employee, string $leaveType, int $year = null): ?LeaveBalance
    {
        $year = $year ?? now()->year;

        return LeaveBalance::where('employee_id', $employee->id)
            ->where('leave_type', $leaveType)
            ->where('year', $year)
            ->first();
    }

    /**
     * Submit a leave request.
     */
    public function submitLeaveRequest(Employee $employee, array $data): LeaveRequest
    {
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $leaveType = $data['leave_type'] ?? 'vacation';

        // Calculate business days (excluding weekends)
        $businessDays = $this->calculateBusinessDays($startDate, $endDate);

        // Get leave balance
        $balance = $this->getLeaveBalance($employee, $leaveType);

        // Create request
        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_requested' => $businessDays,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Reserve balance (mark as pending)
        if ($balance && $leaveType !== 'unpaid') {
            $balance->addPending($businessDays);
        }

        return $request;
    }

    /**
     * Approve a leave request.
     */
    public function approveLeaveRequest(LeaveRequest $request, int $approverId, string $notes = ''): LeaveRequest
    {
        $request->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approval_notes' => $notes,
            'approved_at' => now(),
        ]);

        // Deduct from balance
        $balance = $this->getLeaveBalance(
            $request->employee,
            $request->leave_type,
            $request->start_date->year
        );

        if ($balance && $request->leave_type !== 'unpaid') {
            $balance->deductBalance($request->days_requested);
        }

        return $request;
    }

    /**
     * Reject a leave request.
     */
    public function rejectLeaveRequest(LeaveRequest $request, int $rejectedBy, string $reason = ''): LeaveRequest
    {
        $request->update([
            'status' => 'rejected',
            'approved_by' => $rejectedBy,
            'approval_notes' => $reason,
            'approved_at' => now(),
        ]);

        // Remove from pending balance
        $balance = $this->getLeaveBalance(
            $request->employee,
            $request->leave_type,
            $request->start_date->year
        );

        if ($balance && $request->leave_type !== 'unpaid') {
            $balance->removePending($request->days_requested);
        }

        return $request;
    }

    /**
     * Get all leave requests for an employee.
     */
    public function getLeaveRequests(Employee $employee, string $status = null): Collection
    {
        $query = $employee->leaveRequests();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('start_date', 'desc')->get();
    }

    /**
     * Get pending leave requests (for approval).
     */
    public function getPendingLeaveRequests(int $managerId = null): Collection
    {
        $query = LeaveRequest::where('status', 'pending');

        if ($managerId) {
            $query->whereHas('employee', function ($q) use ($managerId) {
                $q->where('manager_id', $managerId);
            });
        }

        return $query->orderBy('start_date')->get();
    }

    /**
     * Calculate business days between two dates (excluding weekends).
     */
    private function calculateBusinessDays(Carbon $startDate, Carbon $endDate): float
    {
        $days = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (!$current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Get leave balance report for an employee.
     */
    public function getLeaveBalanceReport(Employee $employee, int $year = null): array
    {
        $year = $year ?? now()->year;

        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $year)
            ->get();

        $report = [];

        foreach ($balances as $balance) {
            $report[$balance->leave_type] = [
                'accrual' => $balance->accrual_days_per_year,
                'balance' => $balance->balance,
                'used' => $balance->used,
                'pending' => $balance->pending,
                'available' => $balance->getAvailableBalance(),
                'type_label' => self::LEAVE_TYPES[$balance->leave_type] ?? $balance->leave_type,
            ];
        }

        return $report;
    }

    /**
     * Detect absence-related issues (conflicts, FMLA requirements).
     */
    public function detectAbsenceIssues(LeaveRequest $request): array
    {
        $issues = [];

        // Check for overlapping requests
        $overlapping = LeaveRequest::where('employee_id', $request->employee_id)
            ->where('status', 'approved')
            ->where('id', '!=', $request->id)
            ->whereBetween('start_date', [$request->start_date, $request->end_date])
            ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
            ->exists();

        if ($overlapping) {
            $issues[] = [
                'type' => 'overlapping_request',
                'severity' => 'high',
                'message' => 'Employee already has approved leave during this period',
            ];
        }

        // Check for FMLA eligibility
        if ($request->leave_type === 'maternity' || $request->leave_type === 'paternity') {
            if (!$this->isFmlaEligible($request->employee)) {
                $issues[] = [
                    'type' => 'fmla_ineligible',
                    'severity' => 'critical',
                    'message' => 'Employee may not be FMLA eligible. Legal review required.',
                ];
            }
        }

        // Check balance
        if ($request->leave_type !== 'unpaid') {
            $balance = $this->getLeaveBalance(
                $request->employee,
                $request->leave_type,
                $request->start_date->year
            );

            if ($balance && !$balance->hasBalance($request->days_requested)) {
                $issues[] = [
                    'type' => 'insufficient_balance',
                    'severity' => 'medium',
                    'message' => "Employee has only {$balance->balance} days available, needs {$request->days_requested}",
                ];
            }
        }

        return $issues;
    }

    /**
     * Check if employee is FMLA eligible.
     * FMLA requires: 12+ months employment + 1250+ hours worked in 12 months
     */
    private function isFmlaEligible(Employee $employee): bool
    {
        if (!$employee->hire_date) {
            return false;
        }

        // Check 12 months employment
        if ($employee->hire_date->diffInMonths(now()) < 12) {
            return false;
        }

        // Check 1250 hours worked (would need time tracking integration)
        // For now, just check employment length
        return true;
    }

    /**
     * Accrue leave for an employee on their review date.
     */
    public function accrueLeaveForEmployee(Employee $employee, string $leaveType = null): int
    {
        $accrued = 0;

        if ($leaveType) {
            $balance = $this->getLeaveBalance($employee, $leaveType);
            if ($balance) {
                $accrualAmount = $this->getAnnualAccrualDays($leaveType) / 12; // Monthly accrual
                $balance->update(['balance' => $balance->balance + $accrualAmount]);
                $accrued++;
            }
        } else {
            // Accrue all leave types
            foreach (self::LEAVE_TYPES as $type => $label) {
                if ($this->accrueLeaveForEmployee($employee, $type)) {
                    $accrued++;
                }
            }
        }

        return $accrued;
    }
}
