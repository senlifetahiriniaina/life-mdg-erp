<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\BudgetScenario;
use Modules\Accounting\Services\ScenarioPlanningService;

/**
 * @group Accounting
 *
 * Manage ScenarioPlanning resources in Accounting module.
 */
class ScenarioPlanningController extends Controller
{
    public function __construct(private readonly ScenarioPlanningService $scenarioService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', BudgetScenario::class);

        return response()->json($this->scenarioService->listScenarios());
    }

    public function create(Request $request)
    {
        $this->authorize('create', BudgetScenario::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:revenue,cost,pricing,volume',
            'parameters' => 'required|array',
        ]);

        $scenario = $this->scenarioService->createScenario(
            $request->input('name'),
            $request->input('type'),
            $request->input('parameters')
        );

        return response()->json($scenario, 201);
    }

    public function simulate(Request $request)
    {
        $this->authorize('viewAny', BudgetScenario::class);

        $request->validate([
            'scenario_id' => 'required|exists:acc_budget_scenarios,id',
        ]);

        $simulation = $this->scenarioService->runSimulation(
            (int) $request->scenario_id
        );

        return response()->json($simulation);
    }

    public function compare(Request $request)
    {
        $this->authorize('viewAny', BudgetScenario::class);

        $request->validate([
            'scenario_ids' => 'required|array|min:2',
            'scenario_ids.*' => 'exists:acc_budget_scenarios,id',
        ]);

        $comparison = $this->scenarioService->compareScenarios(
            $request->input('scenario_ids')
        );

        return response()->json($comparison);
    }

    public function sensitivity(Request $request)
    {
        $this->authorize('viewAny', BudgetScenario::class);

        $request->validate([
            'variable' => 'required|string',
            'range_min' => 'required|numeric',
            'range_max' => 'required|numeric',
            'step' => 'nullable|numeric',
        ]);

        $analysis = $this->scenarioService->sensitivityAnalysis(
            $request->input('variable'),
            (float) $request->input('range_min'),
            (float) $request->input('range_max'),
            (float) $request->input('step', 10)
        );

        return response()->json($analysis);
    }

    public function impact(Request $request)
    {
        $this->authorize('viewAny', BudgetScenario::class);

        $request->validate([
            'scenario_id' => 'required|exists:acc_budget_scenarios,id',
        ]);

        $impact = $this->scenarioService->calculateImpact(
            (int) $request->scenario_id
        );

        return response()->json([
            'revenue_impact' => $impact['revenue'],
            'cost_impact' => $impact['cost'],
            'profit_impact' => $impact['profit'],
            'cash_flow_impact' => $impact['cashflow'],
            'break_even_analysis' => $impact['breakeven'],
        ]);
    }

    public function approve(Request $request)
    {
        $request->validate([
            'scenario_id' => 'required|exists:acc_budget_scenarios,id',
        ]);

        $this->authorize('approve', BudgetScenario::find($request->scenario_id));

        $approved = $this->scenarioService->approveScenario(
            (int) $request->scenario_id
        );

        return response()->json([
            'approved' => $approved,
            'message' => 'Scenario approved and ready for implementation',
        ]);
    }
}
