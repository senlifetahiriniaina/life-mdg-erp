<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Strategy\Models\StrategyScenario;
use Modules\Strategy\Models\StrategyScenarioAssumption;
use Modules\Strategy\Services\ScenarioService;

class ScenarioController extends Controller
{
    public function __construct(private ScenarioService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId  = $request->header('X-Tenant-Id', $request->query('tenant_id', 'default'));
        $scenarios = StrategyScenario::where('tenant_id', $tenantId)
            ->withCount('assumptions')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($scenarios);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'type'         => 'nullable|in:optimistic,realistic,pessimistic,custom',
            'base_plan_id' => 'nullable|exists:strategy_plans,id',
            'probability'  => 'nullable|numeric|min:0|max:100',
            'status'       => 'nullable|in:draft,active,archived',
        ]);

        $tenantId = $request->header('X-Tenant-Id', 'default');
        $userId   = $request->user()->id;

        $scenario = $this->service->createScenario($tenantId, $validated, $userId);

        return response()->json($scenario, 201);
    }

    public function show(int $id): JsonResponse
    {
        $scenario = StrategyScenario::with('assumptions')->findOrFail($id);

        return response()->json($scenario);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type'        => 'nullable|in:optimistic,realistic,pessimistic,custom',
            'probability' => 'nullable|numeric|min:0|max:100',
            'status'      => 'nullable|in:draft,active,archived',
        ]);

        $scenario = StrategyScenario::findOrFail($id);
        $scenario->update($validated);

        return response()->json($scenario->fresh());
    }

    public function addAssumption(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'variable_name'  => 'required|string|max:255',
            'description'    => 'nullable|string',
            'base_value'     => 'required|numeric',
            'adjusted_value' => 'required|numeric',
            'impact_scope'   => 'nullable|string|max:255',
        ]);

        $assumption = $this->service->addAssumption($id, $validated);

        return response()->json($assumption, 201);
    }

    public function updateAssumption(Request $request, int $id, int $aId): JsonResponse
    {
        $validated = $request->validate([
            'variable_name'  => 'sometimes|string|max:255',
            'base_value'     => 'sometimes|numeric',
            'adjusted_value' => 'sometimes|numeric',
            'impact_scope'   => 'nullable|string|max:255',
        ]);

        $assumption = StrategyScenarioAssumption::where('scenario_id', $id)->findOrFail($aId);
        $assumption->update($validated);

        return response()->json($assumption->fresh());
    }

    public function impact(int $id): JsonResponse
    {
        $impact = $this->service->computeImpact($id);

        return response()->json($impact);
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'scenario_ids'   => 'required|array|min:2',
            'scenario_ids.*' => 'integer|exists:strategy_scenarios,id',
        ]);

        $comparison = $this->service->compareScenarios($request->input('scenario_ids'));

        return response()->json($comparison);
    }
}
