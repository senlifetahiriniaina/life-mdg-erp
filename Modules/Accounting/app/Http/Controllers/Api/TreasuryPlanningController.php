<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\CashFlowLine;
use Modules\Accounting\Models\TreasuryForecast;
use Modules\Accounting\Services\TreasuryService;

/**
 * @group Accounting
 *
 * Manage TreasuryPlanning resources in Accounting module.
 */
class TreasuryPlanningController extends Controller
{
    public function __construct(private readonly TreasuryService $service) {}

    // ─── Forecast CRUD ───────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $forecasts = TreasuryForecast::with('createdBy')
            ->latest()
            ->paginate(15);

        return response()->json($forecasts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'status' => 'sometimes|in:draft,active,archived',
            'opening_balance' => 'sometimes|numeric',
            'currency' => 'sometimes|string|size:3',
            'notes' => 'nullable|string',
            'lines' => 'sometimes|array',
        ]);

        $lines = $validated['lines'] ?? [];
        unset($validated['lines']);

        $forecast = $this->service->createForecast($validated, $lines);

        return response()->json($forecast->load(['lines', 'scenarios']), 201);
    }

    public function show(TreasuryForecast $forecast): JsonResponse
    {
        return response()->json($forecast->load(['lines', 'scenarios']));
    }

    public function update(Request $request, TreasuryForecast $forecast): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date',
            'status' => 'sometimes|in:draft,active,archived',
            'opening_balance' => 'sometimes|numeric',
            'currency' => 'sometimes|string|size:3',
            'notes' => 'nullable|string',
        ]);

        $forecast->update($validated);

        return response()->json($forecast);
    }

    public function destroy(TreasuryForecast $forecast): JsonResponse
    {
        $forecast->delete();

        return response()->json(null, 204);
    }

    // ─── Lines ────────────────────────────────────────────────────────────────

    public function addLine(Request $request, TreasuryForecast $forecast): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|in:sales_revenue,service_revenue,investment,loan_proceeds,other_income,salaries,rent,utilities,marketing,loan_repayment,tax_payment,other_expense',
            'flow_type' => 'required|in:inflow,outflow',
            'amount' => 'required|numeric|min:0.0001',
            'description' => 'nullable|string|max:255',
            'expected_date' => 'required|date',
            'is_recurring' => 'sometimes|boolean',
            'recurrence_period' => 'nullable|in:weekly,monthly,quarterly,annually',
            'probability' => 'sometimes|numeric|between:0,100',
            'actual_amount' => 'nullable|numeric',
        ]);

        $line = $this->service->addLine($forecast, $validated);

        return response()->json($line, 201);
    }

    public function removeLine(TreasuryForecast $forecast, CashFlowLine $line): JsonResponse
    {
        $this->service->removeLine($line);

        return response()->json(null, 204);
    }

    // ─── Recompute ───────────────────────────────────────────────────────────

    public function recompute(TreasuryForecast $forecast): JsonResponse
    {
        $forecast = $this->service->recompute($forecast);

        return response()->json($forecast);
    }

    // ─── Scenarios ───────────────────────────────────────────────────────────

    public function scenarios(TreasuryForecast $forecast): JsonResponse
    {
        return response()->json($forecast->scenarios()->get());
    }

    public function createScenario(Request $request, TreasuryForecast $forecast): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:base,optimistic,pessimistic',
            'adjustment_factor' => 'sometimes|numeric|between:0,9.9999',
            'notes' => 'nullable|string',
        ]);

        $scenario = $this->service->createScenario($forecast, $validated);

        return response()->json($scenario, 201);
    }

    public function runAnalysis(TreasuryForecast $forecast): JsonResponse
    {
        $scenarios = $this->service->runScenarioAnalysis($forecast);

        return response()->json($scenarios);
    }

    // ─── Projection ──────────────────────────────────────────────────────────

    public function projection(Request $request): JsonResponse
    {
        $months = (int) $request->input('months', 12);
        $opening = (float) $request->input('opening_balance', 0.0);

        $projection = $this->service->getMonthlyProjection($opening, $months);

        return response()->json(['projection' => $projection]);
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        return response()->json($this->service->getDashboard());
    }

    // ─── Realize Line ────────────────────────────────────────────────────────

    public function realizeLine(Request $request, CashFlowLine $line): JsonResponse
    {
        $validated = $request->validate([
            'actual_amount' => 'required|numeric',
        ]);

        $line = $this->service->realizeLine($line, (float) $validated['actual_amount']);

        return response()->json($line);
    }
}
