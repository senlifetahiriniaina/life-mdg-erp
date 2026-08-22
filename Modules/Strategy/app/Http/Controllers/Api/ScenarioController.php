<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyScenario;
use Modules\Strategy\Models\StrategyScenarioAssumption;
use Modules\Strategy\Services\ScenarioService;

class ScenarioController extends Controller
{
    public function __construct(private ScenarioService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
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

        $tenantId = $this->tenantId($request);
        $userId   = $request->user()->id;

        $scenario = $this->service->createScenario($tenantId, $validated, $userId);

        return response()->json($scenario, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $scenario = $this->scenarioInTenant($id, $this->tenantId($request), with: 'assumptions');

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

        $scenario = $this->scenarioInTenant($id, $this->tenantId($request));
        $scenario->update($validated);

        return response()->json($scenario->fresh());
    }

    /**
     * Chantier 32.27: `Route::apiResource('scenarios', ...)` registers
     * `DELETE scenarios/{scenario}` against destroy(), which never existed
     * on this controller — confirmed via reflection, a guaranteed fatal
     * error on every real call.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $scenario = $this->scenarioInTenant($id, $this->tenantId($request));
        $scenario->delete();

        return response()->json(['message' => 'Scenario deleted.']);
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

        $this->scenarioInTenant($id, $this->tenantId($request));

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

        $this->scenarioInTenant($id, $this->tenantId($request));

        $assumption = StrategyScenarioAssumption::where('scenario_id', $id)->findOrFail($aId);
        $assumption->update($validated);

        return response()->json($assumption->fresh());
    }

    public function impact(Request $request, int $id): JsonResponse
    {
        $this->scenarioInTenant($id, $this->tenantId($request));

        $impact = $this->service->computeImpact($id);

        return response()->json($impact);
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'scenario_ids'   => 'required|array|min:2',
            'scenario_ids.*' => 'integer|exists:strategy_scenarios,id',
        ]);

        // Chantier 32.27: `exists:strategy_scenarios,id` alone doesn't check
        // ownership — confirmed empirically that any user could compare
        // (and read the full detail of) another company's real scenarios by
        // id. Every id in the batch must belong to the caller's own tenant.
        $tenantId = $this->tenantId($request);
        $ids      = $request->input('scenario_ids');
        $owned    = StrategyScenario::whereIn('id', $ids)->where('tenant_id', $tenantId)->pluck('id');
        abort_if($owned->count() !== count($ids), 404);

        $comparison = $this->service->compareScenarios($ids);

        return response()->json($comparison);
    }

    /**
     * Load a scenario and 404 unless it belongs to the caller's own tenant —
     * mirrors OkrController::objectiveInTenant()'s established shape.
     * StrategyScenario carries a real tenant_id column.
     */
    private function scenarioInTenant(int $id, string $tenantId, ?string $with = null): StrategyScenario
    {
        $query    = $with ? StrategyScenario::with($with) : StrategyScenario::query();
        $scenario = $query->findOrFail($id);

        abort_if((string) ($scenario->tenant_id ?? '') !== $tenantId, 404);

        return $scenario;
    }

    /**
     * Chantier 10 (Strategy): was $request->user()?->tenant_id ?? 'default' —
     * the phantom users.tenant_id column, never populated for real users, so
     * every tenant silently collapsed into one shared 'default' bucket (a
     * live cross-tenant leak). See StrategyPlanController::tenantId() for the
     * full rationale; fixed to the real company_id boundary column.
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }
}
