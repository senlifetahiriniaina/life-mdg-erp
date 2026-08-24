<?php

namespace Modules\Timesheets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeTrackingProject;
use Modules\Timesheets\Services\TimesheetService;

/**
 * @group Controllers - Metrics
 *
 * Manage Metrics resources.
 */
class MetricsController extends Controller
{
    public function __construct(
        private TimesheetService $service
    ) {}

    public function employeeMetrics(Request $request, int $user, string $month): JsonResponse
    {
        // Chantier 32.19 (Timesheets deep 14-layer audit): zero ownership
        // check at all — despite the route param literally being named
        // {user}, it's actually consumed as an hr_employees.id (the same ID
        // this module's other endpoints key ownership off), and any
        // authenticated "employee" could pass any other employee's id here
        // and read their total/billable hours, confirmed empirically.
        if ($user !== ($request->user()?->employee?->id ?? 0)) {
            abort_unless($request->user()->hasAnyRole(['admin', 'manager', 'hr-manager']), 403);
        }

        $metrics = $this->service->getTimesheetMetrics(
            employee_id: $user,
            from_date: $month.'-01',
            to_date: date('Y-m-t', strtotime($month.'-01'))
        );

        return response()->json($metrics);
    }

    public function projectMetrics(Request $request, int $project): JsonResponse
    {
        // Chantier 32.19: cross-tenant scoping check, same 404-not-403
        // null-safe pattern already established on TrackingProjectController
        // — a manager/admin of another company could otherwise read any
        // tracking project's budget/hours by id.
        $trackingProject = TimeTrackingProject::findOrFail($project);
        $callerCompanyId = $request->user()?->company_id;
        if ($callerCompanyId !== null && $trackingProject->tenant_id !== null
            && (int) $trackingProject->tenant_id !== (int) $callerCompanyId) {
            abort(404);
        }

        $metrics = $this->service->getProjectMetrics($project);

        return response()->json($metrics);
    }

    public function summary(Request $request): JsonResponse
    {
        // Chantier 19 (Lot 2): compared TimesheetEntry.employee_id (an
        // hr_employees.id) against a subquery of users.id — the same
        // ID-space mismatch bug pattern fixed repeatedly elsewhere in this
        // app — so the department filter never matched any real entry.
        //
        // Chantier 32.19: zero tenant scoping at all beyond that — any
        // authenticated "employee" could read the aggregate hours of every
        // company in the app via this one endpoint, confirmed empirically.
        // TimesheetEntry.tenant_id is real and populated by
        // TimesheetEntryController::store()/TimerService::stop() (see the
        // sibling fix already applied to utilization() in
        // TimesheetAdvancedController) — scoped the same way here.
        $companyId = $request->user()?->company_id;

        $entries = TimesheetEntry::query()
            ->when($companyId, fn ($q) => $q->where('tenant_id', $companyId))
            ->when($request->department_id, fn ($q) => $q->whereIn('employee_id', Employee::where('department_id', $request->department_id)->pluck('id'))
            )
            ->when($request->from_date, fn ($q) => $q->whereDate('entry_date', '>=', $request->from_date))
            ->when($request->to_date, fn ($q) => $q->whereDate('entry_date', '<=', $request->to_date))
            ->get();

        $totalHours = $entries->sum('hours_worked');
        $billableHours = $entries->whereIn('status', ['approved'])->sum('hours_worked');
        $approvedEntries = $entries->where('status', 'approved')->count();
        $pendingEntries = $entries->where('status', 'submitted')->count();

        return response()->json([
            'total_entries' => $entries->count(),
            'total_hours' => $totalHours,
            'billable_hours' => $billableHours,
            'approved_entries' => $approvedEntries,
            'pending_entries' => $pendingEntries,
            'average_hours_per_entry' => $entries->count() > 0 ? round($totalHours / $entries->count(), 2) : 0,
        ]);
    }
}
