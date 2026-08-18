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
        $records = Payslip::query()
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
            'total' => Payslip::count(),
            'draft' => Payslip::where('status', 'draft')->count(),
            'approved' => Payslip::where('status', 'approved')->count(),
            'paid' => Payslip::where('status', 'paid')->count(),
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
