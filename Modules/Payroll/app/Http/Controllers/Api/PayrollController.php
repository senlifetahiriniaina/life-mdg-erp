<?php

declare(strict_types=1);

namespace Modules\Payroll\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\PayrollRecord;
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
     * List payroll records for the current tenant and period.
     */
    public function index(Request $request): JsonResponse
    {
        // Compliance First — RBAC: only HR managers, payroll admins, and super-admins
        abort_unless($request->user()->can('payroll.payslips.view'), 403);

        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $request->input('period', now()->format('Y-m')));
        $start = Carbon::createFromDate((int)$year, (int)$month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $records = PayrollRecord::with('employee:id,first_name,last_name,employee_number')
            ->whereHas('employee', fn($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->whereBetween('period_start', [$start, $end])
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
        abort_unless($request->user()->can('payroll.payslips.generate'), 403);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $validated['period']);
        $start = Carbon::createFromDate((int)$year, (int)$month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $result = $this->service->generatePayslips(
            auth()->user()->tenant_id,
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
        abort_unless($request->user()->can('payroll.payslips.approve'), 403);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $validated['period']);
        $start = Carbon::createFromDate((int)$year, (int)$month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $count = PayrollRecord::whereHas('employee', fn($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->whereBetween('period_start', [$start, $end])
            ->where('status', 'draft')
            ->update(['status' => 'approved']);

        return response()->json(['approved_count' => $count]);
    }

    /**
     * Process payment for approved payslips in a period.
     */
    public function processPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $validated['period']);
        $start = Carbon::createFromDate((int)$year, (int)$month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $records = PayrollRecord::whereHas('employee', fn($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->whereBetween('period_start', [$start, $end])
            ->where('status', 'approved')
            ->get();

        $accountingResult = $this->service->postPayslipsToAccounting($records->pluck('id')->toArray());

        $records->each(fn($r) => $r->update(['status' => 'paid', 'payment_date' => now()]));

        return response()->json([
            'paid_count'   => $records->count(),
            'accounting'   => $accountingResult,
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
        $start = Carbon::createFromDate((int)$year, (int)$month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $summary = $this->service->getPayrollSummary(auth()->user()->tenant_id, $start, $end);

        return response()->json(['statistics' => array_merge($summary, ['currency' => 'XOF'])]);
    }

    /**
     * Tax breakdown by country for a period.
     */
    public function taxesByCountry(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $request->input('period', now()->format('Y-m')));
        $start = Carbon::createFromDate((int)$year, (int)$month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $records = PayrollRecord::with('employee:id,first_name,last_name,country_code')
            ->whereHas('employee', fn($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->whereBetween('period_start', [$start, $end])
            ->get();

        $taxes = $records->groupBy(fn($r) => $r->employee->country_code ?? 'SN')
            ->map(function ($group, $country) {
                $totalGross = $group->sum('gross_salary');
                return [
                    'country_code'   => $country,
                    'employee_count' => $group->count(),
                    'total_gross'    => $totalGross,
                    'total_tax'      => $this->service->calculateIncomeTax($totalGross, $country),
                    'social_security'=> $this->service->calculateSocialSecurity($totalGross, $country),
                    'health_insurance'=> 0,
                ];
            })
            ->values();

        return response()->json(['taxes' => $taxes]);
    }
}
