<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Http\Resources\EmployeeResource;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\SalaryBand;
use Modules\Payroll\Models\Payslip;

class EmployeeWebController extends Controller
{
    public function index(Request $request): Response
    {
        // Chantier 32: unconditional company_id scoping — same finding as
        // the API EmployeeController::index() (see its comment) — this web
        // page server-renders full employee detail into Inertia props
        // regardless of the frontend's own display logic, so a real leak
        // here is independent of and just as real as the API's.
        $employees = Employee::query()
            ->with(['department'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%");
            }))
            ->paginate(25)->withQueryString();

        return Inertia::render('HR/Employees/Index', ['employees' => $employees]);
    }

    public function show(Employee $employee): Response
    {
        // Chantier 32: relying on the updated EmployeePolicy's sameCompany
        // check rather than trusting Laravel's route-model-binding alone to
        // have found the right (same-company) record.
        $this->authorize('view', $employee);

        // Chantier 32: the raw $employee model used to be handed straight to
        // Inertia::render() — since Inertia props are serialized directly
        // into the page's initial HTML, this embedded every PII field
        // (bank_details_encrypted, national_id, passport_number — all
        // decrypted plaintext via the model's own accessors) into the page
        // source of a route gated by nothing more than plain `auth` (any
        // authenticated user, no role restriction). The exact same class of
        // PII leak already fixed once for the API side of this module
        // (Chantier 8.3's SelfServiceEmployeeResource) was still live here.
        // Now goes through the same EmployeeResource the real employees API
        // already uses, which never exposes those fields at all.
        $employee->load('department', 'jobPosition');

        // Chantier 32.17: "Soldes de congés" on Employees/Show.vue was
        // 100% hardcoded mock data (`{ type: 'Congés annuels', total: 20,
        // remaining: 12 }`, literal array, no prop, no fetch) — the same
        // class of bug already fixed once for Leave/Analytics.vue at
        // Chantier 8.3. Computed here with the identical days_per_year-
        // minus-days_taken formula EmployeeSelfServiceController::
        // leaveBalance()/EmployeePortalController::leaveBalance() already
        // use for the self-service case.
        $takenByType = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereYear('start_date', now()->year)
            ->selectRaw('leave_type_id, SUM(days) as days_taken')
            ->groupBy('leave_type_id')
            ->pluck('days_taken', 'leave_type_id');

        $leaveBalances = LeaveType::where('is_active', true)->get()->map(fn (LeaveType $type) => [
            'type' => $type->name,
            'total' => (float) $type->days_per_year,
            'remaining' => (float) max(0, $type->days_per_year - ($takenByType[$type->id] ?? 0)),
        ])->values();

        // Chantier 32.17: the "Salaire" field always rendered '—' —
        // EmployeeResource never exposed a `salary` key (by design, PII
        // discipline) and `Employee.salary` isn't even a real column
        // (the real, live salary source is EmployeeCompensation, per the
        // pattern already established for Payroll — see
        // PayrollIntegrationService::getCurrentCompensation()). Resolved
        // the same way here.
        $currentCompensation = $employee->compensations()
            ->where('effective_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString());
            })
            ->latest('effective_date')
            ->first();

        return Inertia::render('HR/Employees/Show', [
            'employee' => array_merge(
                (new EmployeeResource($employee))->toArray(request()),
                [
                    'current_salary' => $currentCompensation?->base_salary,
                    'salary_currency' => $currentCompensation?->currency,
                ],
            ),
            'leaveBalances' => $leaveBalances,
        ]);
    }

    // Chantier 8.3: Employees/Form.vue is real (POSTs/PUTs to the real
    // employees API, navigates via the real hr.employees.index route) but
    // had no web route at all — HR/Employees/Index.vue's "Ajouter" button
    // and HR/Employees/Show.vue's "Edit Employee" link both pointed nowhere.
    public function create(): Response
    {
        return Inertia::render('HR/Employees/Form');
    }

    public function edit(Employee $employee): Response
    {
        // Chantier 32: same rationale as show() above — this also
        // server-renders full employee detail into props.
        $this->authorize('view', $employee);

        return Inertia::render('HR/Employees/Form', ['employee' => $employee]);
    }

    public function payroll(Request $request): Response
    {
        // Chantier 19 (HR): this queried every company's Payslip rows with
        // zero tenant scoping at all — a real, empirically-confirmed
        // cross-tenant leak (an hr-manager from Company A saw Company B's
        // payslips, names, and gross/net salary figures) independent from
        // and never caught by Payroll's own already-fixed (Chantier 10)
        // PayrollController::tenantId(), since this HR web page duplicates
        // the query rather than going through that controller. Payslip.
        // tenant_id is populated from the paying employee's linked
        // User::company_id (see PayrollController::tenantId()'s own
        // docblock) — scoped the same way here.
        $tenantId = (int) ($request->user()->company_id ?? 0);

        $records = Payslip::query()
            ->where('tenant_id', $tenantId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where('employee_name', 'like', "%{$request->search}%"))
            ->latest('period')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->id,
                'employee_name' => $r->employee_name ?? '—',
                'period' => $r->period?->format('M Y'),
                'gross_salary' => $r->gross_salary,
                'net_salary' => $r->net_salary,
                'status' => $r->status,
                'paid_at' => $r->paid_at?->format('M d, Y'),
            ]);

        $stats = [
            'total' => Payslip::where('tenant_id', $tenantId)->count(),
            'draft' => Payslip::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'approved' => Payslip::where('tenant_id', $tenantId)->where('status', 'approved')->count(),
            'paid' => Payslip::where('tenant_id', $tenantId)->where('status', 'paid')->count(),
        ];

        return Inertia::render('HR/Payroll/Index', [
            'records' => $records,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function attendance(Request $request): Response
    {
        return Inertia::render('HR/Attendance/Index');
    }

    public function schedule(Request $request): Response
    {
        return Inertia::render('HR/Shifts/Schedule');
    }

    public function portal(Request $request): Response
    {
        return Inertia::render('HR/Portal');
    }

    public function compensation(Request $request): Response
    {
        // Chantier 32: unconditional company_id scoping — SalaryBand is what
        // this page actually lists (not a single Employee), so the same
        // ->where('company_id', ...) filtering applies to it directly.
        $bands = SalaryBand::query()
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderBy('level')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('HR/Compensation/Index', [
            'bands' => $bands,
            'filters' => $request->only(['search']),
        ]);
    }
}
