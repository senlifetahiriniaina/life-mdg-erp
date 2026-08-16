<?php

declare(strict_types=1);

namespace Modules\Payroll\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Services\PayrollIntegrationService;

/**
 * @group Payroll
 *
 * Payroll processing, payslip management, and OHADA accounting integration.
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollIntegrationService $service) {}

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

        $count = Payslip::where('tenant_id', $this->tenantId($request))
            ->whereDate('period', $periodDate)
            ->where('status', 'draft')
            ->update(['status' => 'approved']);

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
        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $request->input('period', now()->format('Y-m')));
        $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();

        $summary = $this->service->getPayrollSummary($this->tenantId($request), $start, $start);

        return response()->json(['statistics' => array_merge($summary, ['currency' => 'XOF'])]);
    }

    /**
     * Tax breakdown by country for a period.
     */
    public function taxesByCountry(Request $request): JsonResponse
    {
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

        $taxes = $records
            ->groupBy(fn ($r) => $countryByEmployee->get($r->employee_id) ?? 'SN')
            ->when(
                $request->filled('country'),
                fn ($groups) => $groups->only([strtoupper((string) $request->input('country'))])
            )
            ->map(function ($group, $country) {
                $totalGross = (float) $group->sum('gross_salary');

                return [
                    'country_code'     => $country,
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

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->tenant_id ?? 0);
    }
}
