<?php

declare(strict_types=1);

namespace Modules\Payroll\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Payroll\Data\StatutorySchemes;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollIntegrationService;
use Modules\Payroll\Services\PayrollService;

/**
 * @group Payroll
 *
 * Payroll processing, payslip management, and OHADA accounting integration.
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollIntegrationService $service,
        // Chantier 32.18 (Payroll deep audit): PayrollService's run-lifecycle
        // methods (validateRun()/processRun()) already existed, real and
        // tested via their one live Workflow-automation consumer
        // (createRun()), but no real controller ever called them — confirmed
        // empirically that approveBatch()/processPayment() below updated
        // every affected Payslip's status directly while the PayrollRun
        // header record grouping them (its own status/total_gross/
        // total_deductions/total_net/validated_at) stayed frozen at
        // 'draft'/0 forever, even after every payslip underneath it was
        // paid. Wired in below rather than left as a silent data-integrity
        // gap.
        private readonly PayrollService $payrollService,
    ) {}

    /**
     * List payslips for the current tenant and period.
     */
    public function index(Request $request): JsonResponse
    {
        // Compliance First — RBAC: only HR managers, payroll admins, and super-admins
        abort_unless($request->user()->can('payroll.payslip.view'), 403);

        $request->validate([
            'period'      => ['nullable', 'date_format:Y-m'],
            'month'       => ['nullable', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $period = $request->input('period', $request->input('month', now()->format('Y-m')));
        [$year, $month] = explode('-', $period);
        $periodDate = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();

        $records = Payslip::where('tenant_id', $this->tenantId($request))
            ->whereDate('period', $periodDate)
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->latest()
            ->paginate(50);

        return response()->json(['payslips' => $records]);
    }

    /**
     * Generate payslips for all active employees in a period.
     *
     * @bodyParam period string required Period in Y-m format. Example: 2026-05
     */
    public function generate(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payroll.payslip.generate'), 403);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $validated['period']);
        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $result = $this->service->generatePayslips(
            $this->tenantId($request),
            $start,
            $end
        );

        return response()->json($result, 201);
    }

    /**
     * Approve all draft payslips for a period.
     */
    public function approveBatch(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payroll.payslip.approve'), 403);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $validated['period']);
        $periodDate = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();

        // Chantier 32.18: was a bulk Payslip::where(...)->update(...) query-
        // builder update — Eloquent model events (and therefore
        // PayslipObserver, wired in above) never fire on a mass query-
        // builder update, only on a real model save. Switched to iterate
        // and save each record individually, matching processPayment()'s
        // own already-correct $records->each(fn ($r) => $r->update(...))
        // pattern immediately below, so an employee is actually notified
        // when their payslip is approved.
        $draftPayslips = Payslip::where('tenant_id', $this->tenantId($request))
            ->whereDate('period', $periodDate)
            ->where('status', 'draft')
            ->get();
        $draftPayslips->each(fn ($p) => $p->update(['status' => 'approved']));
        $count = $draftPayslips->count();

        // Chantier 32.18: keep the PayrollRun header record (the same
        // ['tenant_id','period']-unique run every Payslip in this batch
        // points at) in sync with its payslips' real approval state,
        // instead of leaving it frozen at 'draft' forever.
        $run = PayrollRun::where('tenant_id', $this->tenantId($request))
            ->whereDate('period', $periodDate)
            ->first();
        if ($run) {
            $this->payrollService->validateRun($run);
        }

        return response()->json(['approved_count' => $count]);
    }

    /**
     * Process payment for approved payslips in a period.
     */
    public function processPayment(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payroll.payslip.approve'), 403);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $validated['period']);
        $periodDate = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();

        $records = Payslip::where('tenant_id', $this->tenantId($request))
            ->whereDate('period', $periodDate)
            ->where('status', 'approved')
            ->get();

        $accountingResult = $this->service->postPayslipsToAccounting($records->pluck('id')->toArray());

        $records->each(fn ($r) => $r->update(['status' => 'paid', 'paid_at' => now()]));

        // Chantier 32.18: recompute the run's totals from its now-paid
        // payslips (processRun()) then mark it paid — PayrollService's own
        // markAsPaid() was deliberately NOT reused here: it flips every
        // still-DRAFT payslip straight to 'paid' unconditionally, bypassing
        // the approval step this controller enforces above (only
        // 'approved' payslips are ever paid here) — that would be a real
        // business-rule violation, not a lifecycle-sync fix.
        $run = PayrollRun::where('tenant_id', $this->tenantId($request))
            ->whereDate('period', $periodDate)
            ->first();
        if ($run) {
            $this->payrollService->processRun($run);
            $run->update(['status' => 'paid']);
        }

        return response()->json([
            'paid_count' => $records->count(),
            'accounting' => $accountingResult,
        ]);
    }

    /**
     * Payroll statistics for a period.
     */
    public function statistics(Request $request): JsonResponse
    {
        // Chantier 8.3: previously uncovered by any authorize() check —
        // accountant/finance-manager (accounting.*/bi.*/strategy.* only, no
        // payroll.* permissions) could read payroll aggregates by passing
        // only the outer route role: gate, matching every other method here.
        abort_unless($request->user()->can('payroll.payslip.view'), 403);

        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $request->input('period', now()->format('Y-m')));
        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();

        // Chantier 32.18: getPayrollSummary() now derives the real currency
        // itself (from the period's own payslips, or the tenant's Company
        // record) — this used to unconditionally overwrite it with a
        // hardcoded 'XOF' regardless of the real data, confirmed empirically
        // to render "XOF 525,000" on a real MGA payslip's dashboard card.
        $summary = $this->service->getPayrollSummary($this->tenantId($request), $start, $start);

        return response()->json(['statistics' => $summary]);
    }

    /**
     * Tax breakdown by country for a period.
     */
    public function taxesByCountry(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('payroll.payslip.view'), 403);

        $request->validate([
            'period'  => ['nullable', 'date_format:Y-m'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        [$year, $month] = explode('-', $request->input('period', now()->format('Y-m')));
        $periodDate = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth()->toDateString();

        $records = Payslip::where('tenant_id', $this->tenantId($request))
            ->whereDate('period', $periodDate)
            ->get();

        $countryByEmployee = Employee::whereIn('id', $records->pluck('employee_id')->unique())
            ->pluck('nationality', 'id');

        // Chantier 19 Lot 2: $records is an Illuminate\Database\Eloquent\
        // Collection — groupBy() on it returns another Eloquent Collection
        // whose "items" are themselves per-group Collections, not models.
        // Eloquent\Collection::only() overrides the base Collection's
        // array-key filtering to instead assume every item is a model with
        // a real getKey() — confirmed via a real request with ?country=SN
        // against real seeded payslip data: a guaranteed
        // BadMethodCallException("Method ...Collection::getKey does not
        // exist") on every call that both has at least one payslip AND
        // filters by ?country=, invisible until now because the only
        // pre-existing test hitting this branch (PayrollApiTest) always ran
        // against an empty Payslip table, where the dictionary build inside
        // only() short-circuits before ever touching an item. toBase()
        // demotes the collection to a plain Illuminate\Support\Collection
        // right after fetching, so groupBy()/only() both use the base,
        // array-key-based semantics this code actually relies on.
        $taxes = $records
            ->toBase()
            ->groupBy(fn ($r) => $countryByEmployee->get($r->employee_id) ?? 'SN')
            ->when(
                $request->filled('country'),
                fn ($groups) => $groups->only([strtoupper((string) $request->input('country'))])
            )
            ->map(function ($group, $country) {
                $totalGross = (float) $group->sum('gross_salary');

                return [
                    'country_code'     => $country,
                    // Chantier 19 Lot 2: Dashboard/Index.vue's "Taxes by
                    // Country" tab renders country.country_name, but this
                    // response never included it (only the raw code) — the
                    // card header always rendered blank for real data.
                    // StatutorySchemes already carries a real localized
                    // name per country (reused, not invented); any code
                    // outside its 8 supported countries falls back to the
                    // code itself rather than a guessed name.
                    'country_name'     => StatutorySchemes::country($country)['name'] ?? $country,
                    'employee_count'   => $group->count(),
                    'total_gross'      => $totalGross,
                    'total_tax'        => $this->service->calculateIncomeTax($totalGross, $country),
                    'social_security'  => $this->service->calculateSocialSecurity($totalGross, $country),
                    'health_insurance' => 0,
                ];
            })
            ->values();

        return response()->json(['taxes' => $taxes]);
    }

    /**
     * Self-service: the authenticated user's own payslips — PayrollPolicy's
     * "employee can view their own payslip" ability existed but had no
     * consumer of any kind (unregistered, no controller call site).
     */
    public function myPayslips(Request $request): JsonResponse
    {
        $employeeId = $request->user()->employee?->id;
        abort_if($employeeId === null, 403, 'No linked employee record.');

        $records = Payslip::where('employee_id', $employeeId)
            ->latest('period')
            ->paginate(50);

        return response()->json(['payslips' => $records]);
    }

    /**
     * View a single payslip — payroll staff can view any, an employee only
     * their own (PayrollPolicy::view()).
     */
    public function show(Request $request, Payslip $payslip): JsonResponse
    {
        $this->authorize('view', $payslip);

        return response()->json(['payslip' => $payslip]);
    }

    /**
     * Chantier 10: was $request->user()->tenant_id -- users.tenant_id is the
     * well-documented phantom column (real, migrated, never in
     * User::$fillable, never populated by the real registration flow —
     * confirmed via a repo-wide grep of every write path) that has caused
     * real cross-tenant leaks fixed repeatedly this session (Reporting,
     * Strategy, AI, Sales, Achats, Integration, Workflow). Since tenant_id
     * is effectively always null/0 in production, every company's payslips
     * were silently collapsing into one shared tenant_id=0 bucket. The real
     * tenant boundary is users.company_id.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->company_id ?? 0);
    }
}
