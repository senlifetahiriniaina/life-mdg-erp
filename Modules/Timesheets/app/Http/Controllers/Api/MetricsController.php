<?php

namespace Modules\Timesheets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Models\TimesheetEntry;
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
        $metrics = $this->service->getTimesheetMetrics(
            employee_id: $user,
            from_date: $month.'-01',
            to_date: date('Y-m-t', strtotime($month.'-01'))
        );

        return response()->json($metrics);
    }

    public function projectMetrics(Request $request, int $project): JsonResponse
    {
        $metrics = $this->service->getProjectMetrics($project);

        return response()->json($metrics);
    }

    public function summary(Request $request): JsonResponse
    {
        // Chantier 19 (Lot 2): compared TimesheetEntry.employee_id (an
        // hr_employees.id) against a subquery of users.id — the same
        // ID-space mismatch bug pattern fixed repeatedly elsewhere in this
        // app — so the department filter never matched any real entry.
        $entries = TimesheetEntry::query()
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
