<?php

namespace Modules\HR\Services;

use Modules\HR\Models\Attendance;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\Position;

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

    public function getAllDepartments($perPage = 15)
    {
        return Department::withCount('employees')
            ->orderBy('name')
            ->paginate($perPage);
    }

    // Position Management
    public function createPosition(array $data): Position
    {
        return Position::create($data);
    }

    public function updatePosition(Position $position, array $data): Position
    {
        $position->update($data);

        return $position;
    }

    public function getPositionsByDepartment(int $departmentId)
    {
        return Position::where('department_id', $departmentId)
            ->withCount('employees')
            ->get();
    }

    // Leave Management
    public function requestLeave(array $data): LeaveRequest
    {
        $data['status'] = $data['status'] ?? 'pending';
        return LeaveRequest::create($data);
    }

    public function approveLeave(LeaveRequest $request, int $approverId, string $notes = ''): LeaveRequest
    {
        $request->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approval_notes' => $notes,
            'approved_at' => now(),
        ]);

        return $request;
    }

    public function rejectLeave(LeaveRequest $request, int $approverId, string $notes = ''): LeaveRequest
    {
        $request->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approval_notes' => $notes,
            'approved_at' => now(),
        ]);

        return $request;
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
            'hired_this_month' => Employee::where('hire_date', '>=', now()->startOfMonth())->count(),
        ];
    }

    public function getDepartmentMetrics(Department $department)
    {
        return [
            'total_employees' => $department->getEmployeeCount(),
            'positions' => $department->positions()->count(),
            'open_positions' => $department->positions()->sum('headcount') - $department->getEmployeeCount(),
            'total_payroll' => $department->employees()->sum('salary'),
            'budget_allocated' => $department->budget_allocation,
            'budget_utilization' => $department->budget_allocation ? (($department->employees()->sum('salary') / $department->budget_allocation) * 100) : 0,
        ];
    }
}
