<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\HR\Services\CompensationService;

/**
 * @group HR - Employee Compensation
 * Per-employee compensation tracking (base salary, bonus, benefits, equity
 * vesting), distinct from SalaryBandController's band-level equity analysis.
 */
class CompensationController extends Controller
{
    public function __construct(private readonly CompensationService $service) {}

    public function current(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return response()->json($this->service->getCurrentCompensation($employee));
    }

    public function breakdown(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return response()->json($this->service->getCompensationBreakdown($employee));
    }

    public function history(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return response()->json($this->service->getCompensationHistory($employee));
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'base_salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'bonus_frequency' => ['nullable', 'string', 'in:annual,semi-annual,quarterly'],
            'equity_granted' => ['nullable', 'numeric', 'min:0'],
            'equity_vesting_period_months' => ['nullable', 'integer', 'min:1'],
            'benefits_annual_value' => ['nullable', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return response()->json($this->service->createCompensation($employee->id, $validated), 201);
    }

    public function updateVesting(Employee $employee, EmployeeCompensation $compensation): JsonResponse
    {
        $this->authorize('update', $employee);

        $this->service->updateEquityVesting($compensation);

        return response()->json($compensation->fresh());
    }

    public function bonusAccrual(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $period = $request->query('period', 'month');

        return response()->json([
            'employee_id' => $employee->id,
            'period' => $period,
            'accrued_amount' => $this->service->calculateBonusAccrual($employee, $period),
        ]);
    }

    public function benchmark(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $validated = $request->validate([
            'market_median' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->service->compareToMarketBenchmark($employee, (float) $validated['market_median']));
    }

    public function audit(): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        return response()->json(['issues' => $this->service->auditCompensationRecords()]);
    }
}
