<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\Position;

class HrDashboardService
{
    /**
     * Return core HR stats: headcount, absences today, avg tenure.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $headcount = Employee::where('status', 'active')->count();

        $absentToday = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();

        // Average tenure in months for active employees who have a hire_date
        $avgTenureMonths = Employee::where('status', 'active')
            ->whereNotNull('hire_date')
            ->get()
            ->avg(fn (Employee $e) => $e->hire_date ? now()->diffInMonths(Carbon::parse($e->hire_date)) : 0);

        $openPositions = (int) Position::sum('headcount') - $headcount;

        return [
            'headcount' => $headcount,
            'absences_today' => $absentToday,
            'open_positions' => $openPositions,
            'avg_tenure_months' => round((float) $avgTenureMonths, 1),
        ];
    }

    /**
     * Return employee count grouped by department.
     *
     * @return array<string, int>
     */
    public function getDepartmentDistribution(): array
    {
        $rows = Employee::where('hr_employees.status', 'active')
            ->join('hr_departments', 'hr_departments.id', '=', 'hr_employees.department_id')
            ->groupBy('hr_departments.id', 'hr_departments.name')
            ->select('hr_departments.name', DB::raw('count(*) as total'))
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->name] = (int) $row->total;
        }

        // Employees without a department
        $unassigned = Employee::where('hr_employees.status', 'active')->whereNull('department_id')->count();
        if ($unassigned > 0) {
            $result['Unassigned'] = $unassigned;
        }

        return $result;
    }

    /**
     * Return leave statistics: pending requests count + approved this calendar month.
     *
     * @return array<string, int>
     */
    public function getLeaveStats(): array
    {
        $pending = LeaveRequest::where('status', 'pending')->count();

        $approvedThisMonth = LeaveRequest::where('status', 'approved')
            ->whereMonth('approved_at', now()->month)
            ->whereYear('approved_at', now()->year)
            ->count();

        return [
            'pending_requests' => $pending,
            'approved_this_month' => $approvedThisMonth,
        ];
    }

    /**
     * Return recruitment funnel by candidate stage.
     *
     * @return array<string, int>
     */
    public function getRecruitmentFunnel(): array
    {
        // Job postings grouped by status as a lightweight funnel
        $rows = DB::table('hr_job_postings')
            ->groupBy('status')
            ->select('status', DB::raw('count(*) as total'))
            ->get();

        $funnel = [];
        foreach ($rows as $row) {
            $funnel[$row->status] = (int) $row->total;
        }

        return $funnel;
    }

    /**
     * Return today's attendance summary.
     *
     * @return array<string, int>
     */
    public function getAttendanceToday(): array
    {
        $totalActive = Employee::where('status', 'active')->count();

        // Present: clocked in today (any type except remote)
        $present = AttendanceRecord::whereDate('clock_in', today())
            ->where('type', '!=', 'remote')
            ->distinct('employee_id')
            ->count('employee_id');

        // Remote: clocked in today with type = remote
        $remote = AttendanceRecord::whereDate('clock_in', today())
            ->where('type', 'remote')
            ->distinct('employee_id')
            ->count('employee_id');

        // On leave today
        $onLeave = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();

        $absent = max(0, $totalActive - $present - $remote - $onLeave);

        return [
            'present' => $present,
            'remote' => $remote,
            'on_leave' => $onLeave,
            'absent' => $absent,
            'total' => $totalActive,
        ];
    }
}
