<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

class HrDashboardService
{
    /**
     * Return core HR stats: headcount, absences today, avg tenure.
     *
     * @return array<string, mixed>
     */
    // Chantier 32: threaded an optional $companyId through every aggregate
    // below — this module had zero company/tenant scoping anywhere at all
    // (see EmployeePolicy's docblock for the full rationale); the dashboard
    // controller now passes the acting user's real company_id.
    public function getStats(?int $companyId = null): array
    {
        $headcount = Employee::where('status', 'active')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $absentToday = LeaveRequest::where('status', 'approved')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();

        // Average tenure in months for active employees who have a hire_date
        // Chantier 19 (HR): was now()->diffInMonths(Carbon::parse($e->hire_date))
        // — Carbon 3 changed diffInMonths()'s $absolute default from true
        // (Carbon 2) to false, so $this->diffInMonths($other) now returns
        // $other-$this rather than an always-positive magnitude. With
        // $this=now() and $other=a past hire_date, every real employee
        // produced a *negative* tenure, confirmed empirically via tinker —
        // dashboard/HR/Dashboard.vue's "avg_tenure_months" KPI was always
        // negative for any real, seeded company. Fixed by computing from
        // the hire_date's own perspective (matching the already-correct
        // CompensationService::calculateMonthsEmployed() pattern), which
        // yields other(now)-this(hire_date) = a positive value.
        $avgTenureMonths = Employee::where('status', 'active')
            ->whereNotNull('hire_date')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->get()
            ->avg(fn (Employee $e) => $e->hire_date ? Carbon::parse($e->hire_date)->diffInMonths(now()) : 0);

        // Chantier 19 (HR) originally clamped this to 0 rather than surface a
        // misleading negative number, since Modules\HR\Models\Position (table
        // hr_positions) was a confirmed-dead, always-empty model — zero
        // routes/controllers anywhere referenced it (only JobPosition, a
        // genuinely different, real/populated model with no headcount-target
        // field of its own, is actually routed/used) and nothing in this
        // app's real write paths ever populated hr_positions.
        // Chantier 32.17 (HR deep 14-layer audit): Position + hr_positions
        // dropped for good (Layer 9 fake/dead — see the migration and
        // Department::jobPositions() docblock). Still no real per-position
        // headcount-target data source anywhere in this trimmed HR scope to
        // compute a genuine open-positions figure from (building one would
        // mean adding a new field/UI, not fixing existing wiring) — kept at
        // the same 0 fallback, matching this app's established
        // fallback-first degradation pattern (see Strategy's training_roi/
        // time_to_fill ratios).
        $openPositions = 0;

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
    public function getDepartmentDistribution(?int $companyId = null): array
    {
        $rows = Employee::where('hr_employees.status', 'active')
            ->when($companyId !== null, fn ($q) => $q->where('hr_employees.company_id', $companyId))
            ->join('hr_departments', 'hr_departments.id', '=', 'hr_employees.department_id')
            ->groupBy('hr_departments.id', 'hr_departments.name')
            ->select('hr_departments.name', DB::raw('count(*) as total'))
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->name] = (int) $row->total;
        }

        // Employees without a department
        $unassigned = Employee::where('hr_employees.status', 'active')
            ->when($companyId !== null, fn ($q) => $q->where('hr_employees.company_id', $companyId))
            ->whereNull('department_id')->count();
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
    public function getLeaveStats(?int $companyId = null): array
    {
        $pending = LeaveRequest::where('status', 'pending')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        $approvedThisMonth = LeaveRequest::where('status', 'approved')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
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
    public function getAttendanceToday(?int $companyId = null): array
    {
        $totalActive = Employee::where('status', 'active')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        // Present: clocked in today (any type except remote)
        $present = AttendanceRecord::whereDate('clock_in', today())
            ->where('type', '!=', 'remote')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->distinct('employee_id')
            ->count('employee_id');

        // Remote: clocked in today with type = remote
        $remote = AttendanceRecord::whereDate('clock_in', today())
            ->where('type', 'remote')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->distinct('employee_id')
            ->count('employee_id');

        // On leave today
        $onLeave = LeaveRequest::where('status', 'approved')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
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

    /**
     * Leave analytics for a given year (optionally scoped to a department):
     * totals, approval rate, monthly trend, per-type breakdown, and
     * per-employee balances. Uses the same days_per_year-minus-taken formula
     * already used by EmployeeSelfServiceController/EmployeePortalController
     * for a single employee's own balance, just aggregated across everyone.
     *
     * @return array<string, mixed>
     */
    // Chantier 32: threaded an optional $companyId through — same rationale
    // as getStats()/getDepartmentDistribution() above.
    public function getLeaveAnalytics(int $year, ?int $departmentId = null, ?int $companyId = null): array
    {
        $requestsQuery = LeaveRequest::query()
            ->whereYear('start_date', $year)
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)));

        $totalTaken = (int) (clone $requestsQuery)->where('status', 'approved')->sum('days');
        $pendingRequests = (int) (clone $requestsQuery)->where('status', 'pending')->count();
        $approvedCount = (int) (clone $requestsQuery)->where('status', 'approved')->count();
        $rejectedCount = (int) (clone $requestsQuery)->where('status', 'rejected')->count();
        $decided = $approvedCount + $rejectedCount;
        $approvalRate = $decided > 0 ? round(($approvedCount / $decided) * 100, 1) : 0.0;

        $monthlyTrend = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyTrend[] = [
                'month' => $month,
                'count' => (int) (clone $requestsQuery)
                    ->where('status', 'approved')
                    ->whereMonth('start_date', $month)
                    ->count(),
            ];
        }

        $leaveTypeStats = LeaveType::query()
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->withCount(['leaveRequests as taken_count' => function ($q) use ($year, $departmentId) {
                $q->where('status', 'approved')
                    ->whereYear('start_date', $year)
                    ->when($departmentId, fn ($qq) => $qq->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)));
            }])
            ->get(['id', 'name', 'code'])
            ->map(fn (LeaveType $type) => [
                'name' => $type->name,
                'code' => $type->code,
                'count' => $type->taken_count,
            ]);

        $employees = Employee::query()
            ->where('status', 'active')
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with('department:id,name')
            ->get(['id', 'first_name', 'last_name', 'department_id']);

        $leaveTypes = LeaveType::where('is_active', true)
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'days_per_year']);
        $totalAnnualDays = (float) $leaveTypes->sum('days_per_year');

        $takenByEmployee = LeaveRequest::where('status', 'approved')
            ->whereYear('start_date', $year)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->selectRaw('employee_id, SUM(days) as days_taken')
            ->groupBy('employee_id')
            ->pluck('days_taken', 'employee_id');

        $employeeBalances = $employees->map(function (Employee $employee) use ($takenByEmployee, $totalAnnualDays) {
            $taken = (float) ($takenByEmployee[$employee->id] ?? 0);

            return [
                'employee_id' => $employee->id,
                'name' => trim($employee->first_name.' '.$employee->last_name),
                'department' => $employee->department?->name,
                'taken' => $taken,
                'remaining' => max(0.0, $totalAnnualDays - $taken),
            ];
        })->values();

        $avgBalance = $employeeBalances->isNotEmpty()
            ? round($employeeBalances->avg('remaining'), 1)
            : 0.0;

        return [
            'total_taken' => $totalTaken,
            'avg_balance' => $avgBalance,
            'pending_requests' => $pendingRequests,
            'approval_rate' => $approvalRate,
            'monthly_trend' => $monthlyTrend,
            'leave_type_stats' => $leaveTypeStats,
            'employee_balances' => $employeeBalances,
        ];
    }
}
