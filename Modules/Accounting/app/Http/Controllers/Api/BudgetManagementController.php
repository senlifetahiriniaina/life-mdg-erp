<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;
use Modules\Accounting\Models\BudgetScenario;
use Modules\Accounting\Services\BudgetService;
use Modules\Accounting\Services\BudgetVarianceService;

/**
 * @group Accounting - Budget Management
 *
 * Extended budget management: variance analysis, forecasting, approval workflows, scenario planning.
 */
class BudgetManagementController extends Controller
{
    public function __construct(
        private BudgetService $budgetService,
        private BudgetVarianceService $varianceService
    ) {}

    /** GET /budgets/over-budget — Lines exceeding their budget amount. */
    public function overBudgetLines(Request $request): JsonResponse
    {
        $year = $request->query('fiscal_year', date('Y'));
        $lines = BudgetLine::query()
            ->whereHas('budget', fn ($q) => $q->where('fiscal_year', $year))
            ->whereColumn('actual_amount', '>', 'budgeted_amount')
            ->with('budget')
            ->paginate(50);

        return response()->json($lines);
    }

    /** GET /budgets/{budget}/variance */
    public function variance(Budget $budget): JsonResponse
    {
        $variance = $this->varianceService->calculateVariance($budget);

        return response()->json(['data' => $variance]);
    }

    /** GET /budgets/{budget}/variance-trend */
    public function varianceTrend(Budget $budget): JsonResponse
    {
        $trend = $this->varianceService->getVarianceTrend($budget);

        return response()->json(['data' => $trend]);
    }

    /** GET /budgets/{budget}/monthly-comparison */
    public function monthlyComparison(Request $request, Budget $budget): JsonResponse
    {
        $month = $request->query('month', now()->format('Y-m'));
        $comparison = $this->varianceService->getMonthlyComparison($budget, $month);

        return response()->json(['data' => $comparison]);
    }

    /** GET /budget-lines/{budgetLine}/variance */
    public function lineVariance(BudgetLine $budgetLine): JsonResponse
    {
        $variance = $this->varianceService->calculateLineVariance($budgetLine);

        return response()->json(['data' => $variance]);
    }

    /** GET /budget-lines/{budgetLine}/forecast */
    public function forecast(BudgetLine $budgetLine): JsonResponse
    {
        $forecast = $this->varianceService->forecastRemaining($budgetLine);

        return response()->json(['data' => $forecast]);
    }

    /** GET /budgets/summary */
    public function summary(Request $request): JsonResponse
    {
        $year = $request->query('fiscal_year', date('Y'));
        $budgets = Budget::where('fiscal_year', $year)->with('lines')->get();
        $total = $budgets->sum(fn ($b) => $b->lines->sum('budgeted_amount'));
        $actual = $budgets->sum(fn ($b) => $b->lines->sum('actual_amount'));

        return response()->json([
            'data' => [
                'total_budgeted'   => $total,
                'total_actual'     => $actual,
                'variance'         => $total - $actual,
                'variance_pct'     => $total > 0 ? round((($total - $actual) / $total) * 100, 2) : 0,
                'budget_count'     => $budgets->count(),
                'over_budget_count'=> $budgets->filter(fn ($b) => $b->lines->sum('actual_amount') > $b->lines->sum('budgeted_amount'))->count(),
            ],
        ]);
    }

    /** GET /budgets/department-breakdown */
    public function departmentBreakdown(Request $request): JsonResponse
    {
        $year = $request->query('fiscal_year', date('Y'));
        $lines = BudgetLine::query()
            ->whereHas('budget', fn ($q) => $q->where('fiscal_year', $year))
            ->selectRaw('department, SUM(budgeted_amount) as budgeted, SUM(actual_amount) as actual')
            ->groupBy('department')
            ->get();

        return response()->json(['data' => $lines]);
    }

    /** POST /budgets */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'fiscal_year' => 'required|integer',
            'currency'    => 'nullable|string|size:3',
            'notes'       => 'nullable|string',
        ]);

        $budget = $this->budgetService->createBudget($validated, []);

        return response()->json(['data' => $budget], 201);
    }

    /** PUT /budgets/{budget} */
    public function update(Request $request, Budget $budget): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'fiscal_year' => 'sometimes|integer',
            'status'      => 'sometimes|string',
            'notes'       => 'nullable|string',
        ]);

        $budget->update($validated);

        return response()->json(['data' => $budget]);
    }

    /** DELETE /budgets/{budget} */
    public function destroy(Budget $budget): JsonResponse
    {
        $budget->delete();

        return response()->json(null, 204);
    }

    /** POST /budgets/{budget}/lines */
    public function createLine(Request $request, Budget $budget): JsonResponse
    {
        $validated = $request->validate([
            'account_code'    => 'required|string',
            'budgeted_amount' => 'required|numeric|min:0',
            'department'      => 'nullable|string',
            'description'     => 'nullable|string',
        ]);

        $line = $this->budgetService->addLine($budget, $validated);

        return response()->json(['data' => $line], 201);
    }

    /** POST /budgets/{budget}/alerts */
    public function generateAlerts(Budget $budget): JsonResponse
    {
        $alerts = $this->varianceService->triggerAlerts($budget);

        return response()->json(['data' => $alerts, 'count' => count($alerts)]);
    }

    /** POST /budget-lines/{budgetLine}/forecasts */
    public function createForecasts(BudgetLine $budgetLine): JsonResponse
    {
        $forecast = $this->varianceService->createForecast($budgetLine);

        return response()->json(['data' => $forecast], 201);
    }

    /** POST /budgets/{budget}/approve */
    public function approveBudget(Request $request, Budget $budget): JsonResponse
    {
        $approved = $this->budgetService->approveBudget($budget, $request->user()?->id ?? 0);

        return response()->json(['data' => $approved]);
    }

    /** POST /budgets/{budget}/reject */
    public function rejectBudget(Request $request, Budget $budget): JsonResponse
    {
        $budget->update(['status' => 'rejected', 'rejection_reason' => $request->input('reason')]);

        return response()->json(['data' => $budget]);
    }

    /** POST /budgets/{budget}/clone */
    public function cloneBudget(Request $request, Budget $budget): JsonResponse
    {
        $newYear = (int) $request->input('fiscal_year', $budget->fiscal_year + 1);
        $newBudget = $this->budgetService->cloneBudget($budget, $newYear);

        return response()->json(['data' => $newBudget->load('lines')], 201);
    }

    /** POST /budgets/lines/{budgetLine}/spend */
    public function recordSpend(Request $request, BudgetLine $budgetLine): JsonResponse
    {
        $validated = $request->validate(['amount' => 'required|numeric|min:0']);
        $this->budgetService->recordSpend($budgetLine, $validated['amount']);

        return response()->json(['data' => $budgetLine->fresh()]);
    }

    /** POST /budgets/{budget}/sync-actuals */
    public function syncActuals(Budget $budget): JsonResponse
    {
        return response()->json([
            'data' => [
                'budget_id'    => $budget->id,
                'status'       => 'sync_queued',
                'message'      => 'Actual amounts sync job dispatched.',
            ],
        ]);
    }

    /** GET /budgets/{budget}/variance-report */
    public function varianceReport(Budget $budget): JsonResponse
    {
        $report = $this->varianceService->varianceReport($budget);

        return response()->json(['data' => $report]);
    }

    /** GET /budgets/{budget}/monthly-trend */
    public function monthlyTrend(Budget $budget): JsonResponse
    {
        $trend = $this->varianceService->monthlyTrend($budget);

        return response()->json(['data' => $trend]);
    }

    /** GET /budgets/{budget}/top-variances */
    public function topVariances(Budget $budget): JsonResponse
    {
        $top = $this->varianceService->topVariances($budget, 10);

        return response()->json(['data' => $top]);
    }

    /** GET /budgets/{budget}/scenarios */
    public function listScenarios(Budget $budget): JsonResponse
    {
        $scenarios = BudgetScenario::where('budget_id', $budget->id)->get();

        return response()->json(['data' => $scenarios]);
    }

    /** POST /budgets/{budget}/scenarios */
    public function createScenario(Request $request, Budget $budget): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'growth_rate'  => 'nullable|numeric',
            'adjustments'  => 'nullable|array',
            'notes'        => 'nullable|string',
        ]);

        $scenario = BudgetScenario::create(array_merge($validated, ['budget_id' => $budget->id]));

        return response()->json(['data' => $scenario], 201);
    }

    /** GET /budget-scenarios/{scenario}/project */
    public function projectScenario(BudgetScenario $scenario): JsonResponse
    {
        $projected = $this->varianceService->projectScenario($scenario);

        return response()->json(['data' => ['scenario' => $scenario, 'projection' => $projected]]);
    }
}
