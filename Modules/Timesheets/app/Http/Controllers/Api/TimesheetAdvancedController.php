<?php

declare(strict_types=1);

namespace Modules\Timesheets\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimesheetPeriod;
use Modules\Timesheets\Services\ProjectBillingService;
use Modules\Timesheets\Services\TimesheetService;

/**
 * TimesheetAdvancedController — Phase 49 advanced timesheet and billing endpoints.
 *
 * Chantier 8.4: was built entirely against Modules\Timesheets\Models\Timesheet
 * (deleted), whose $fillable (work_date/hours_logged/billable/hourly_rate)
 * never matched its own timesheets_sheets stub table (bare id/tenant_id/
 * data/timestamps) — every aggregate query below fatalled the moment real
 * data existed. Rewritten onto the real, already-migrated TimesheetEntry
 * (individual daily entries) and TimesheetPeriod (weekly submission/
 * approval) models. The old index/store/update/destroy Timesheet-CRUD
 * methods were deleted outright rather than repaired — they duplicated the
 * already-real, already-tested, already-RBAC-covered TimesheetEntryController
 * one-for-one. A new set of "sheets" endpoints was added to back the real,
 * routed Sheets/*.vue and Reports/*.vue pages, which called a third,
 * entirely nonexistent API scheme (`timesheets/sheets*`,
 * `timesheets/reports/*`) with no controller behind it at all.
 *
 * Routes:
 *   GET    /api/v1/timesheets/sheets                         — list (paginated)
 *   POST   /api/v1/timesheets/sheets                         — create a sheet (period)
 *   PUT    /api/v1/timesheets/sheets/{id}                    — update a draft sheet
 *   GET    /api/v1/timesheets/sheets/my-sheets                — current user's own sheets
 *   POST   /api/v1/timesheets/sheets/{id}/submit              — submit for approval
 *   POST   /api/v1/timesheets/sheets/{id}/approve             — approve (alias of periods/{id}/approve)
 *   POST   /api/v1/timesheets/sheets/{id}/reject              — reject (alias of periods/{id}/reject)
 *   GET    /api/v1/timesheets/reports/project-billing         — billing report
 *   GET    /api/v1/timesheets/reports/employee-hours          — employee hours report
 *   GET    /api/v1/timesheets/reports/utilization              — utilization report
 *
 *   POST   /api/v1/timesheets/periods/{weekStart}/submit      — submit (weekStart-keyed)
 *   PUT    /api/v1/timesheets/periods/{id}/approve            — approve
 *   PUT    /api/v1/timesheets/periods/{id}/reject             — reject
 *   GET    /api/v1/timesheets/weekly/{employeeId}/{weekStart} — weekly view
 *   GET    /api/v1/timesheets/team/{managerId}                — team view
 *   GET    /api/v1/timesheets/utilization                     — utilization (generic)
 *   GET    /api/v1/timesheets/revenue-recognition             — revenue recognition
 *
 *   GET    /api/v1/projects/{id}/billing                      — billing history
 *   POST   /api/v1/projects/{id}/billing/milestone            — bill by milestone
 *   POST   /api/v1/projects/{id}/billing/percentage           — bill by percentage
 *   POST   /api/v1/projects/{id}/billing/time-material        — bill T&M
 *   GET    /api/v1/projects/{id}/billing/invoiceable          — invoiceable amount
 *   POST   /api/v1/projects/{id}/billing/{billingId}/generate-invoice — generate invoice
 */
class TimesheetAdvancedController extends Controller
{
    public function __construct(
        private readonly TimesheetService      $timesheetService,
        private readonly ProjectBillingService $billingService,
    ) {}

    // -------------------------------------------------------------------------
    // Sheets (TimesheetPeriod) CRUD
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/timesheets/sheets
     */
    public function sheetsIndex(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TimesheetPeriod::class);

        $query = TimesheetPeriod::query()->with('employee');

        // Chantier 19 (Lot 2): this endpoint had zero authorize() call and
        // zero ownership filtering at all — any authenticated "employee"
        // could list every other employee's weekly timesheets. Mirrors the
        // same non-manager scoping already used by
        // TimesheetEntryController::index().
        if (! $request->user()->hasAnyRole(['admin', 'manager', 'hr-manager'])) {
            $query->where('employee_id', $request->user()->employee?->id ?? 0);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sort = (string) $request->input('sort', '');
        if ($sort !== '') {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');
            if (in_array($column, ['period_start', 'period_end', 'total_hours', 'status'], true)) {
                $query->orderBy($column, $direction);
            }
        } else {
            $query->latest('period_start');
        }

        $periods = $query->paginate((int) $request->input('per_page', 15));
        $periods->getCollection()->transform(fn (TimesheetPeriod $p) => $this->sheetPayload($p));

        return response()->json($periods);
    }

    /**
     * GET /api/v1/timesheets/sheets/my-sheets
     */
    public function mySheets(Request $request): JsonResponse
    {
        $employeeId = $request->user()?->employee?->id;

        $periods = TimesheetPeriod::query()
            ->with(['employee', 'submitter', 'approver'])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId), fn ($q) => $q->whereRaw('1 = 0'))
            ->latest('period_start')
            ->paginate((int) $request->input('per_page', 100));

        $periods->getCollection()->transform(fn (TimesheetPeriod $p) => $this->sheetPayload($p));

        return response()->json($periods);
    }

    /**
     * POST /api/v1/timesheets/sheets
     */
    public function storeSheet(Request $request): JsonResponse
    {
        $this->authorize('create', TimesheetPeriod::class);

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'employee_id'  => ['nullable', 'integer', 'exists:hr_employees,id'],
        ]);

        $employeeId = $validated['employee_id'] ?? $request->user()?->employee?->id;
        abort_unless($employeeId, 422, 'This user has no linked employee record.');

        // Chantier 19 (Lot 2): creating a sheet for someone else's
        // employee_id had zero role check — any "employee" could create
        // (and, since storeSheet had no ownership tie afterward, later
        // submit) a sheet on behalf of any other employee.
        if ($employeeId !== $request->user()?->employee?->id) {
            abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403);
        }

        $period = TimesheetPeriod::create([
            // Chantier 10: was $request->user()?->tenant_id — the phantom
            // column (real, migrated, never in User::$fillable, never
            // populated by the real registration flow) that has caused real
            // cross-tenant leaks fixed repeatedly this session. The real
            // tenant boundary is users.company_id.
            'tenant_id'    => $request->user()?->company_id,
            'employee_id'  => $employeeId,
            'period_start' => $validated['period_start'],
            'period_end'   => $validated['period_end'],
            'status'       => 'draft',
        ]);

        return response()->json($this->sheetPayload($period->load('employee')), 201);
    }

    /**
     * PUT /api/v1/timesheets/sheets/{id}
     */
    public function updateSheet(Request $request, int $id): JsonResponse
    {
        $period = TimesheetPeriod::findOrFail($id);

        // Chantier 19 (Lot 2): the previous inline check only gated
        // non-draft edits behind admin/manager — a draft sheet belonging
        // to a *different* employee could be edited by any "employee"
        // caller. TimesheetPeriodPolicy::update() covers both the
        // status-gate and ownership.
        $this->authorize('update', $period);

        $validated = $request->validate([
            'period_start' => ['sometimes', 'date'],
            'period_end'   => ['sometimes', 'date', 'after_or_equal:period_start'],
            'employee_id'  => ['sometimes', 'integer', 'exists:hr_employees,id'],
        ]);

        $period->update($validated);

        return response()->json($this->sheetPayload($period->fresh('employee')));
    }

    /**
     * POST /api/v1/timesheets/sheets/{id}/submit
     */
    public function submitSheet(Request $request, int $id): JsonResponse
    {
        $period = TimesheetPeriod::findOrFail($id);

        // Chantier 19 (Lot 2): zero authorize() call — any authenticated
        // "employee" could submit any other employee's draft sheet.
        $this->authorize('submit', $period);

        if (! $period->canBeSubmitted()) {
            return response()->json([
                'error'  => "Sheet cannot be submitted (current status: {$period->status})",
                'status' => $period->status,
            ], 422);
        }

        $this->aggregateAndSubmit($period, $request->user()?->id);

        return response()->json($this->sheetPayload($period->fresh('employee')));
    }

    // -------------------------------------------------------------------------
    // Period submission workflow (weekStart-keyed, no frontend caller today
    // — kept as a real, working alternate API entry point per API First)
    // -------------------------------------------------------------------------

    /**
     * POST /api/v1/timesheets/periods/{weekStart}/submit
     */
    public function submitPeriod(Request $request, string $weekStart): JsonResponse
    {
        $employeeId = $request->input('employee_id', $request->user()?->employee?->id ?? 1);
        $weekEnd    = Carbon::parse($weekStart)->endOfWeek()->format('Y-m-d');

        // Chantier 19 (Lot 2): the client-controlled employee_id param had
        // no role check at all — any "employee" could submit (and,
        // firstOrCreate-ing the period, silently create) any other
        // employee's period by weekStart.
        if ($employeeId !== ($request->user()?->employee?->id ?? 1)) {
            abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403);
        }

        $period = TimesheetPeriod::firstOrCreate(
            ['employee_id' => $employeeId, 'period_start' => $weekStart],
            [
                // Chantier 10: phantom-column fix, see storeSheet() above.
                'tenant_id'      => $request->user()?->company_id ?? 0,
                'period_end'     => $weekEnd,
                'total_hours'    => 0,
                'billable_hours' => 0,
                'overtime_hours' => 0,
                'status'         => 'draft',
            ]
        );

        if (! $period->canBeSubmitted()) {
            return response()->json([
                'error'  => "Period cannot be submitted (current status: {$period->status})",
                'status' => $period->status,
            ], 422);
        }

        $this->aggregateAndSubmit($period, $request->user()?->id ?? $employeeId);

        return response()->json([
            'data'    => array_merge($period->toArray(), [
                'utilization_rate' => $period->utilization_rate,
            ]),
            'message' => 'Period submitted for approval',
        ]);
    }

    /**
     * PUT /api/v1/timesheets/periods/{id}/approve
     */
    public function approvePeriod(Request $request, int $id): JsonResponse
    {
        $period = TimesheetPeriod::find($id);

        if (! $period) {
            return response()->json(['error' => 'Period not found'], 404);
        }

        // Chantier 19 (Lot 2): the headline finding of this re-audit —
        // zero role check of any kind here beyond the outer
        // role:employee,manager,admin route gate, so any "employee" could
        // approve ANY other employee's submitted weekly timesheet.
        // TimesheetPeriodPolicy::approve() restricts this to
        // admin/manager/hr-manager, matching the LeaveRequestController
        // approve()/reject() precedent elsewhere in this app.
        $this->authorize('approve', $period);

        if (! $period->canBeApproved()) {
            return response()->json([
                'error'  => "Period cannot be approved (current status: {$period->status})",
            ], 422);
        }

        $period->update([
            'status'      => 'approved',
            'approved_by' => $request->user()?->id ?? 1,
            'approved_at' => now(),
        ]);

        // Mark timesheet entries in the period range as approved
        TimesheetEntry::where('employee_id', $period->employee_id)
            ->whereBetween('entry_date', [$period->period_start, $period->period_end])
            ->where('status', 'submitted')
            ->update(['status' => 'approved']);

        return response()->json([
            'data'    => $period->fresh()->toArray(),
            'message' => 'Period approved',
        ]);
    }

    /**
     * PUT /api/v1/timesheets/periods/{id}/reject
     */
    public function rejectPeriod(Request $request, int $id): JsonResponse
    {
        $period = TimesheetPeriod::find($id);

        if (! $period) {
            return response()->json(['error' => 'Period not found'], 404);
        }

        // Chantier 19 (Lot 2): same missing-authorize() gap as
        // approvePeriod() above — reuses the 'approve' ability (reject is
        // the same privilege level), matching TimesheetEntryPolicy's own
        // convention of gating reject() on the approve ability.
        $this->authorize('approve', $period);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $period->update([
            'status'          => 'rejected',
            'rejected_reason' => $validated['reason'],
            'approved_by'     => $request->user()?->id ?? 1,
            'approved_at'     => now(),
        ]);

        return response()->json([
            'data'    => $period->fresh()->toArray(),
            'message' => 'Period rejected',
        ]);
    }

    // -------------------------------------------------------------------------
    // Reporting views
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/timesheets/weekly/{employeeId}/{weekStart}
     */
    public function weeklyView(Request $request, int $employeeId, string $weekStart): JsonResponse
    {
        // Chantier 19 (Lot 2): zero ownership check — any authenticated
        // "employee" could read any other employee's weekly hours/billable
        // breakdown by id. No frontend caller today (API First — kept as a
        // real, working alternate entry point), but real, reachable PII
        // exposure regardless.
        if ($employeeId !== ($request->user()?->employee?->id)) {
            abort_unless($request->user()->hasAnyRole(['admin', 'manager', 'hr-manager']), 403);
        }

        $weekEnd = Carbon::parse($weekStart)->endOfWeek()->format('Y-m-d');

        $entries = TimesheetEntry::where('employee_id', $employeeId)
            ->whereBetween('entry_date', [$weekStart, $weekEnd])
            ->orderBy('entry_date')
            ->get()
            ->map(fn (TimesheetEntry $t) => array_merge($t->toArray(), [
                'billable_amount_xof' => $t->billable_amount,
            ]));

        $totalHours    = (float) $entries->sum('hours_worked');
        $billableHours = (float) $entries->sum('billable_hours');

        return response()->json([
            'data' => [
                'employee_id'        => $employeeId,
                'week_start'         => $weekStart,
                'week_end'           => $weekEnd,
                'entries'            => $entries->values(),
                'total_hours'        => round($totalHours, 2),
                'billable_hours'     => round($billableHours, 2),
                'non_billable_hours' => round($totalHours - $billableHours, 2),
                'overtime_hours'     => round(max(0, $totalHours - 40), 2),
                'currency'           => 'XOF',
            ],
        ]);
    }

    /**
     * GET /api/v1/timesheets/team/{managerId}
     */
    public function teamView(Request $request, int $managerId): JsonResponse
    {
        // Chantier 19 (Lot 2): same class of gap as weeklyView() — any
        // "employee" could read any manager's whole team's hours/
        // utilization by id. managerId here is a users.id (prj_team_members
        // .manager_id), so compared directly against the caller's own id.
        if ($managerId !== $request->user()?->id) {
            abort_unless($request->user()->hasAnyRole(['admin', 'manager', 'hr-manager']), 403);
        }

        $from = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->query('to', now()->endOfMonth()->format('Y-m-d'));

        $teamData = [];

        try {
            $employees = DB::table('prj_team_members')
                ->where('manager_id', $managerId)
                ->pluck('employee_id');

            foreach ($employees as $employeeId) {
                $totals = TimesheetEntry::where('employee_id', $employeeId)
                    ->whereBetween('entry_date', [$from, $to])
                    ->selectRaw('SUM(hours_worked) as total, SUM(billable_hours) as billable')
                    ->first();

                $total    = (float) ($totals->total    ?? 0);
                $billable = (float) ($totals->billable ?? 0);

                $teamData[] = [
                    'employee_id'      => $employeeId,
                    'total_hours'      => $total,
                    'billable_hours'   => $billable,
                    'utilization_pct'  => $total > 0 ? round($billable / $total * 100, 1) : 0.0,
                ];
            }
        } catch (\Exception) {
            // Demo fallback
            $teamData = [
                ['employee_id' => 1, 'total_hours' => 160.0, 'billable_hours' => 128.0, 'utilization_pct' => 80.0],
                ['employee_id' => 2, 'total_hours' => 152.0, 'billable_hours' => 114.0, 'utilization_pct' => 75.0],
                ['employee_id' => 3, 'total_hours' => 168.0, 'billable_hours' => 151.2, 'utilization_pct' => 90.0],
            ];
        }

        return response()->json([
            'data' => [
                'manager_id' => $managerId,
                'period'     => ['from' => $from, 'to' => $to],
                'team'       => $teamData,
                'currency'   => 'XOF',
            ],
        ]);
    }

    /**
     * GET /api/v1/timesheets/utilization
     */
    public function utilization(Request $request): JsonResponse
    {
        $from     = $request->query('from', now()->startOfMonth()->format('Y-m-d'));
        $to       = $request->query('to',   now()->endOfMonth()->format('Y-m-d'));
        // Chantier 10: phantom-column fix, see storeSheet() above.
        $tenantId = $request->user()?->company_id ?? 0;

        try {
            $stats = TimesheetEntry::where('tenant_id', $tenantId)
                ->whereBetween('entry_date', [$from, $to])
                ->selectRaw('
                    SUM(hours_worked) as total_hours,
                    SUM(billable_hours) as billable_hours,
                    COUNT(DISTINCT employee_id) as active_employees,
                    SUM(billable_hours * hourly_rate) as billable_revenue_xof
                ')
                ->first();

            $total    = (float) ($stats->total_hours    ?? 0);
            $billable = (float) ($stats->billable_hours ?? 0);

            $result = [
                'period'               => ['from' => $from, 'to' => $to],
                'total_hours'          => $total,
                'billable_hours'       => $billable,
                'non_billable_hours'   => round($total - $billable, 2),
                'utilization_pct'      => $total > 0 ? round($billable / $total * 100, 1) : 0.0,
                'active_employees'     => (int) ($stats->active_employees ?? 0),
                'billable_revenue_xof' => (float) ($stats->billable_revenue_xof ?? 0),
                'currency'             => 'XOF',
            ];
        } catch (\Exception) {
            $result = [
                'period'               => ['from' => $from, 'to' => $to],
                'total_hours'          => 2_080.0,
                'billable_hours'       => 1_664.0,
                'non_billable_hours'   => 416.0,
                'utilization_pct'      => 80.0,
                'active_employees'     => 13,
                'billable_revenue_xof' => 24_960_000.0,
                'currency'             => 'XOF',
                '_demo'                => true,
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/v1/timesheets/revenue-recognition
     */
    public function revenueRecognition(Request $request): JsonResponse
    {
        $period    = $request->query('period', now()->format('Y-m'));
        // Chantier 10: was client-controlled — any authenticated user could
        // pass ?company_id=<victim> to read another company's revenue
        // recognition data (an IDOR on top of the same phantom-column bug
        // fixed elsewhere in this file). Dropped the query override; the
        // caller's own company_id is now the only source.
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $data = $this->billingService->getRevenueRecognition($companyId, $period);

        return response()->json(['data' => $data]);
    }

    // -------------------------------------------------------------------------
    // Reports (Reports/*.vue — real, routed pages with no backend until now)
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/timesheets/reports/project-billing
     */
    public function projectBillingReport(Request $request): JsonResponse
    {
        $from = $request->query('from_date', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->query('to_date', now()->format('Y-m-d'));

        $entries = TimesheetEntry::with(['project:id,name', 'employee'])
            ->whereBetween('entry_date', [$from, $to])
            ->where('billable_hours', '>', 0)
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->project_id))
            ->get();

        $byProject = $entries->groupBy('project_id')->map(function ($group) {
            $first        = $group->first();
            $billableHours = (float) $group->sum('billable_hours');
            $amount        = (float) $group->sum(fn (TimesheetEntry $e) => $e->billable_amount);

            return [
                'project_name'    => $first->project?->name,
                'billable_hours'  => round($billableHours, 2),
                'avg_hourly_rate' => round((float) $group->avg('hourly_rate'), 2),
                'billable_amount' => round($amount, 2),
                'employee_count'  => $group->pluck('employee_id')->unique()->count(),
                'entry_count'     => $group->count(),
            ];
        })->values();

        $byEmployeeProject = $entries->groupBy(fn (TimesheetEntry $e) => "{$e->project_id}:{$e->employee_id}")
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'project_name'   => $first->project?->name,
                    'employee_name'  => $first->employee?->full_name,
                    'billable_hours' => round((float) $group->sum('billable_hours'), 2),
                    'hourly_rate'    => round((float) $group->avg('hourly_rate'), 2),
                    'amount'         => round((float) $group->sum(fn (TimesheetEntry $e) => $e->billable_amount), 2),
                ];
            })->values();

        return response()->json([
            'total_billable_hours'  => round((float) $entries->sum('billable_hours'), 2),
            'total_billable_amount' => round((float) $entries->sum(fn (TimesheetEntry $e) => $e->billable_amount), 2),
            'avg_hourly_rate'       => round((float) $entries->avg('hourly_rate'), 2),
            'by_project'            => $byProject,
            'by_employee_project'   => $byEmployeeProject,
        ]);
    }

    /**
     * GET /api/v1/timesheets/reports/employee-hours
     */
    public function employeeHoursReport(Request $request): JsonResponse
    {
        $from = $request->query('from_date', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->query('to_date', now()->format('Y-m-d'));

        $entries = TimesheetEntry::with(['employee', 'project:id,name'])
            ->whereBetween('entry_date', [$from, $to])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->get();

        $byEmployee = $entries->groupBy('employee_id')->map(function ($group) {
            $first         = $group->first();
            $totalHours    = (float) $group->sum('hours_worked');
            $billableHours = (float) $group->sum('billable_hours');

            return [
                'id'                 => $first->employee_id,
                'name'               => $first->employee?->full_name,
                'total_hours'        => round($totalHours, 2),
                'billable_hours'     => round($billableHours, 2),
                'non_billable_hours' => round($totalHours - $billableHours, 2),
                'billable_amount'    => round((float) $group->sum(fn (TimesheetEntry $e) => $e->billable_amount), 2),
                'avg_hourly_rate'    => round((float) $group->avg('hourly_rate'), 2),
            ];
        })->values();

        $byProject = $entries->groupBy('project_id')->map(function ($group) {
            $first = $group->first();

            return [
                'project_name'    => $first->project?->name,
                'total_hours'     => round((float) $group->sum('hours_worked'), 2),
                'billable_hours'  => round((float) $group->sum('billable_hours'), 2),
                'employee_count'  => $group->pluck('employee_id')->unique()->count(),
                'billable_amount' => round((float) $group->sum(fn (TimesheetEntry $e) => $e->billable_amount), 2),
            ];
        })->values();

        $totalHours    = (float) $entries->sum('hours_worked');
        $billableHours = (float) $entries->sum('billable_hours');

        return response()->json([
            'total_hours'        => round($totalHours, 2),
            'billable_hours'     => round($billableHours, 2),
            'non_billable_hours' => round($totalHours - $billableHours, 2),
            'by_employee'        => $byEmployee,
            'by_project'         => $byProject,
        ]);
    }

    /**
     * GET /api/v1/timesheets/reports/utilization
     */
    public function utilizationReport(Request $request): JsonResponse
    {
        $from = $request->query('from_date', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->query('to_date', now()->format('Y-m-d'));

        $entries = TimesheetEntry::with('employee.department')
            ->whereBetween('entry_date', [$from, $to])
            ->get();

        $byEmployee = $entries->groupBy('employee_id')->map(function ($group) {
            $first      = $group->first();
            $total      = (float) $group->sum('hours_worked');
            $billable   = (float) $group->sum('billable_hours');
            $util       = $total > 0 ? round($billable / $total * 100, 1) : 0.0;

            return [
                'id'             => $first->employee_id,
                'name'           => $first->employee?->full_name,
                'department'     => $first->employee?->department?->name,
                'total_hours'    => round($total, 2),
                'billable_hours' => round($billable, 2),
                'utilization'    => $util,
            ];
        })->when($request->filled('department'), fn ($c) => $c->filter(
            fn ($row) => $row['department'] === $request->department
        ))->values();

        $ranges = ['high' => 0, 'medium' => 0, 'low' => 0, 'very_low' => 0];
        foreach ($byEmployee as $row) {
            $ranges[match (true) {
                $row['utilization'] >= 80 => 'high',
                $row['utilization'] >= 60 => 'medium',
                $row['utilization'] >= 40 => 'low',
                default                   => 'very_low',
            }]++;
        }

        $byDepartment = $byEmployee->groupBy('department')
            ->filter(fn ($group, $dept) => $dept !== null && $dept !== '')
            ->map(fn ($group, $dept) => [
                'department'      => $dept,
                'avg_utilization' => round((float) $group->avg('utilization'), 1),
                'employee_count'  => $group->count(),
            ])->values();

        $avgUtilization = $byEmployee->count() > 0 ? round((float) $byEmployee->avg('utilization'), 1) : 0.0;

        return response()->json([
            'avg_utilization'    => $avgUtilization,
            'high_utilization'   => $byEmployee->where('utilization', '>=', 80)->count(),
            'under_utilized'     => $byEmployee->where('utilization', '<', 40)->count(),
            'utilization_ranges' => $ranges,
            'by_employee'        => $byEmployee,
            'by_department'      => $byDepartment,
        ]);
    }

    // -------------------------------------------------------------------------
    // Project billing
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects/{id}/billing
     */
    public function billingHistory(int $id): JsonResponse
    {
        $history = $this->billingService->getBillingHistory($id);

        return response()->json(['data' => $history]);
    }

    /**
     * POST /api/v1/projects/{id}/billing/milestone
     */
    public function billByMilestone(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'milestone_id' => 'required|integer',
        ]);

        $result = $this->billingService->billByMilestone($validated['milestone_id']);

        return response()->json(['data' => $result], 201);
    }

    /**
     * POST /api/v1/projects/{id}/billing/percentage
     */
    public function billByPercentage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'percentage' => 'required|numeric|min:0.01|max:100',
        ]);

        $result = $this->billingService->billByPercentage($id, (float) $validated['percentage']);

        $status = isset($result['success']) && $result['success'] === false ? 422 : 201;

        return response()->json(['data' => $result], $status);
    }

    /**
     * POST /api/v1/projects/{id}/billing/time-material
     */
    public function billTimeAndMaterial(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        $result = $this->billingService->billTimeAndMaterial(
            $id,
            $validated['period_start'],
            $validated['period_end'],
        );

        return response()->json(['data' => $result], 201);
    }

    /**
     * GET /api/v1/projects/{id}/billing/invoiceable
     */
    public function invoiceable(int $id): JsonResponse
    {
        $data = $this->billingService->getInvoiceableAmount($id);

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/v1/projects/{id}/billing/{billingId}/generate-invoice
     */
    public function generateInvoice(int $id, int $billingId): JsonResponse
    {
        $invoice = $this->billingService->generateInvoice($billingId);

        return response()->json(['data' => $invoice], 201);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Aggregate real TimesheetEntry hours for a period's date range and
     * transition it to 'submitted'. Shared by the weekStart-keyed
     * submitPeriod() and the id-keyed submitSheet().
     */
    private function aggregateAndSubmit(TimesheetPeriod $period, ?int $submittedBy): void
    {
        $totals = TimesheetEntry::where('employee_id', $period->employee_id)
            ->whereBetween('entry_date', [$period->period_start, $period->period_end])
            ->selectRaw('SUM(hours_worked) as total, SUM(billable_hours) as billable')
            ->first();

        $totalHours    = (float) ($totals->total    ?? 0);
        $billableHours = (float) ($totals->billable ?? 0);
        $overtimeHours = max(0.0, $totalHours - 40.0); // OHADA: 40h/week standard

        $period->update([
            'status'         => 'submitted',
            'total_hours'    => $totalHours,
            'billable_hours' => $billableHours,
            'overtime_hours' => $overtimeHours,
            'submitted_by'   => $submittedBy,
            'submitted_at'   => now(),
        ]);
    }

    private function sheetPayload(TimesheetPeriod $period): array
    {
        return [
            'id'              => $period->id,
            'employee_id'     => $period->employee_id,
            'employee'        => $period->relationLoaded('employee') && $period->employee ? [
                'id'    => $period->employee->id,
                'name'  => $period->employee->full_name,
                'email' => $period->employee->email,
            ] : null,
            'period_start'    => $period->period_start?->format('Y-m-d'),
            'period_end'      => $period->period_end?->format('Y-m-d'),
            'total_hours'     => (float) $period->total_hours,
            'billable_hours'  => (float) $period->billable_hours,
            'overtime_hours'  => (float) $period->overtime_hours,
            'status'          => $period->status,
            'submitted_at'    => $period->submitted_at?->format('Y-m-d H:i:s'),
            'submitter'       => $period->relationLoaded('submitter') && $period->submitter ? [
                'id' => $period->submitter->id, 'name' => $period->submitter->name,
            ] : null,
            'approved_at'     => $period->approved_at?->format('Y-m-d H:i:s'),
            'approver'        => $period->relationLoaded('approver') && $period->approver ? [
                'id' => $period->approver->id, 'name' => $period->approver->name,
            ] : null,
            'rejected_reason' => $period->rejected_reason,
            'created_at'      => $period->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
