<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\HR\Models\Employee;
use Modules\HR\Models\SalaryBand;
use Modules\Payroll\Models\Payslip;

class EmployeeWebController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = Employee::query()
            ->with(['department'])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%");
            }))
            ->paginate(25)->withQueryString();

        return Inertia::render('HR/Employees/Index', ['employees' => $employees]);
    }

    public function show(Employee $employee): Response
    {
        $employee->load('department');

        return Inertia::render('HR/Employees/Show', ['employee' => $employee]);
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
        $bands = SalaryBand::query()
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
