<?php

namespace Modules\HR\Services;

use Modules\HR\Models\Attendance;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveApprovalLog;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

class HRService
{
    // Employee Management
    public function createEmployee(array $data): Employee
    {
        return Employee::create($data);
    }

    public function updateEmployee(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee;
    }

    public function getEmployee(int $id): ?Employee
    {
        return Employee::with('department', 'position', 'manager')->find($id);
    }

    public function getAllEmployees($perPage = 15)
    {
        return Employee::with('department', 'position')
            ->orderBy('first_name')
            ->paginate($perPage);
    }

    public function getEmployeesByDepartment(int $departmentId)
    {
        return Employee::where('department_id', $departmentId)
            ->with('position')
            ->get();
    }

    // Department Management
    public function createDepartment(array $data): Department
    {
        return Department::create($data);
    }

    public function updateDepartment(Department $department, array $data): Department
    {
        $department->update($data);

        return $department;
    }

    // Chantier 32.17 (HR deep 14-layer audit): $companyId added — see
    // DepartmentController::index()'s own docblock for the full rationale
    // (same cross-tenant leak already fixed on Employee's index()).
    public function getAllDepartments($perPage = 15, ?int $companyId = null)
    {
        return Department::withCount('employees')
            ->with('manager:id,first_name,last_name')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->paginate($perPage);
    }

    // Chantier 32.17 (HR deep 14-layer audit): createPosition()/
    // updatePosition()/getPositionsByDepartment() removed — confirmed
    // zero controller/route consumer anywhere (Layer 9 fake/dead; the
    // model they operated on, Position, and its table hr_positions, were
    // dropped in the same chantier — see the migration's docblock). The
    // real, live, routed org-structure model is JobPosition, already fully
    // served by JobPositionController.

    // Leave Management
    public function requestLeave(array $data): LeaveRequest
    {
        $data['status'] = $data['status'] ?? 'pending';
        return LeaveRequest::create($data);
    }

    // Chantier 31 (HR re-audit): $approverId is now nullable. hr_leave_requests.approved_by
    // is a hard FK to hr_employees.id (nullOnDelete) — the caller must resolve the acting
    // user's linked Employee id, never pass a raw users.id (see the LeaveController fix
    // this same chantier made for the exact bug that mismatch caused). Not every
    // manager/hr-manager/admin necessarily has an hr_employees row, so null must be a safe,
    // non-crashing input here rather than forcing every caller to fabricate an id.
    public function approveLeave(LeaveRequest $request, ?int $approverId, string $notes = '', ?string $approverRole = null): LeaveRequest
    {
        $request->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approval_notes' => $notes,
            'approved_at' => now(),
        ]);

        if ($approverId !== null) {
            $this->logApprovalStep($request, $approverId, $approverRole, 'approved', $notes);
        }

        return $request;
    }

    public function rejectLeave(LeaveRequest $request, ?int $approverId, string $notes = '', ?string $approverRole = null): LeaveRequest
    {
        $request->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approval_notes' => $notes,
            'approved_at' => now(),
        ]);

        if ($approverId !== null) {
            $this->logApprovalStep($request, $approverId, $approverRole, 'rejected', $notes);
        }

        return $request;
    }

    /**
     * Level is simply "how many decisions has this request seen so far,
     * plus one" — enough to drive a simple submitted → manager → HR stepper
     * on the front end without routing leave through the full Validation
     * engine (out of scope, leave approval is time-sensitive HR data).
     */
    private function logApprovalStep(LeaveRequest $request, int $approverId, ?string $approverRole, string $action, string $notes): void
    {
        $level = LeaveApprovalLog::where('leave_request_id', $request->id)->max('level');

        LeaveApprovalLog::create([
            'leave_request_id' => $request->id,
            'level' => ($level ?? 0) + 1,
            'approver_id' => $approverId,
            'approver_role' => $approverRole,
            'action' => $action,
            'comment' => $notes,
            'actioned_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function getPendingLeaveRequests($perPage = 15)
    {
        return LeaveRequest::pending()
            ->with('employee', 'leaveType')
            ->orderBy('start_date')
            ->paginate($perPage);
    }

    public function getEmployeeLeaveBalance(Employee $employee, LeaveType $leaveType)
    {
        $approved = $employee->leaveRequests()
            ->where('leave_type_id', $leaveType->id)
            ->where('status', 'approved')
            ->sum('days_requested');

        return $leaveType->days_per_year - $approved;
    }

    // Attendance Management
    public function recordAttendance(array $data): Attendance
    {
        return Attendance::create($data);
    }

    public function getAttendanceByEmployee(Employee $employee, $month, $year)
    {
        return $employee->attendance()
            ->byMonth($month, $year)
            ->orderBy('attendance_date')
            ->get();
    }

    public function getMonthlyAttendanceReport(int $departmentId, $month, $year)
    {
        $employees = $this->getEmployeesByDepartment($departmentId);

        return $employees->map(function ($employee) use ($month, $year) {
            $attendance = $this->getAttendanceByEmployee($employee, $month, $year);

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->getFullName(),
                'present_days' => $attendance->where('status', 'present')->count(),
                'absent_days' => $attendance->where('status', 'absent')->count(),
                'late_days' => $attendance->where('status', 'late')->count(),
            ];
        });
    }

    // HR Metrics
    public function getHRMetrics()
    {
        return [
            'total_employees' => Employee::active()->count(),
            'departments' => Department::active()->count(),
            'pending_leaves' => LeaveRequest::pending()->count(),
            // Chantier 32.17 (HR deep 14-layer audit): confirmed empirically
            // via tinker that this returned a nonsensical negative number
            // (e.g. -10) on real data — Position::sum('headcount') always
            // returned 0 against the confirmed-dead, always-empty
            // hr_positions table (see the migration that drops it in the
            // same chantier), so this was really just `0 - $activeCount`.
            // Matches the identical fix already applied to
            // HrDashboardService::getStats() at Chantier 19 Lot 2 — no real
            // per-position headcount-target data source exists anywhere in
            // this trimmed HR scope, so kept at the same honest 0 fallback
            // rather than a misleading negative figure.
            'open_positions' => 0,
            'hired_this_month' => Employee::where('hire_date', '>=', now()->startOfMonth())->count(),
        ];
    }

    public function getDepartmentMetrics(Department $department)
    {
        // Chantier 32.17: 'total_payroll'/'budget_utilization' used to call
        // $department->employees()->sum('salary') — 'salary' has never been
        // a real hr_employees column (confirmed via Schema::getColumnListing;
        // the real, live salary source is EmployeeCompensation, same
        // established pattern as PayrollIntegrationService::
        // getCurrentCompensation()) — SQLite silently returned 0 rather than
        // erroring (the identical "unresolved identifier in an aggregate is
        // camouflaged as an empty/zero result" bug class already documented
        // elsewhere in this app's history for Shared\CountryController),
        // confirmed empirically via tinker against 3 real active employees.
        // Fixed to sum each employee's real *current* compensation record.
        $employeeIds = $department->employees()->pluck('id');

        $totalPayroll = \Modules\HR\Models\EmployeeCompensation::whereIn('employee_id', $employeeIds)
            ->where('effective_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->sortByDesc('effective_date')->first())
            ->sum('base_salary');

        return [
            'total_employees' => $department->getEmployeeCount(),
            // Chantier 32.17: replaces the deleted positions() relation
            // (Position model, confirmed dead — see jobPositions()'s own
            // docblock on the Department model). JobPosition has no
            // headcount-target field, so 'open_positions' stays at the same
            // honest 0 fallback as getHRMetrics() above rather than a
            // misleading negative figure.
            'positions' => $department->jobPositions()->count(),
            'open_positions' => 0,
            'total_payroll' => $totalPayroll,
            'budget_allocated' => $department->budget_allocation,
            'budget_utilization' => $department->budget_allocation
                ? (($totalPayroll / $department->budget_allocation) * 100)
                : 0,
        ];
    }
}
