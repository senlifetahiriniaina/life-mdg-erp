<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyKeyResult;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Services\OkrService;

class OkrController extends Controller
{
    public function __construct(private OkrService $service) {}

    public function index(Request $request): JsonResponse
    {
        // Chantier 19 (Lot 5): confirmed empirically (Chantier19InvestigationTest)
        // that this listed every company's objectives with no tenant filter at
        // all whenever plan_id was omitted — the common case for the real
        // Objectives/Index.vue page, which lists the whole OKR tree. Scoped via
        // StrategyObjective::scopeForTenant() (new), same fix shape as every
        // other tenantId() helper in this module.
        $tenantId = $this->tenantId($request);
        $query = StrategyObjective::forTenant($tenantId)->with(['keyResults', 'pillar']);

        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->integer('plan_id'));
        }
        if ($request->has('level')) {
            $query->where('level', $request->input('level'));
        }
        if ($request->has('owner_type')) {
            $query->where('owner_type', $request->input('owner_type'));
        }

        return response()->json($query->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StrategyObjective::class);

        $validated = $request->validate([
            'plan_id'         => 'required|exists:strategy_plans,id',
            'pillar_id'       => 'nullable|exists:strategy_pillars,id',
            'parent_id'       => 'nullable|exists:strategy_objectives,id',
            'level'           => 'required|in:vision,mission,strategic,annual,quarterly,team,individual',
            'owner_type'      => 'nullable|in:company,department,team,user',
            'owner_id'        => 'nullable|integer',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'framework_type'  => 'nullable|in:okr,bsc,hoshin,smart',
            'bsc_perspective' => 'nullable|in:financial,customer,process,learning',
            'weight'          => 'nullable|numeric|min:0',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
            'status'          => 'nullable|in:draft,active,at_risk,behind,completed,cancelled',
        ]);

        // Chantier 19 (Lot 5): `exists:strategy_plans,id` alone doesn't check
        // that the plan belongs to the caller's own company — without this,
        // any user could attach a new objective to another company's plan.
        $tenantId = $this->tenantId($request);
        StrategyPlan::forTenant($tenantId)->findOrFail($validated['plan_id']);

        $objective = $this->service->createObjective($validated);

        return response()->json($objective, 201);
    }

    /**
     * Chantier 32.27: `Route::apiResource('objectives', ...)` registers
     * `GET objectives/{objective}` against show(), which never existed on
     * this controller — confirmed via reflection, a guaranteed fatal error
     * on every real call.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $objective = $this->objectiveInTenant($id, $this->tenantId($request));
        $this->authorize('view', $objective);

        return response()->json($objective->load(['keyResults', 'pillar']));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $objective = $this->objectiveInTenant($id, $this->tenantId($request));

        $this->authorize('update', $objective);

        $validated = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'level'           => 'sometimes|in:vision,mission,strategic,annual,quarterly,team,individual',
            'framework_type'  => 'nullable|in:okr,bsc,hoshin,smart',
            'bsc_perspective' => 'nullable|in:financial,customer,process,learning',
            'weight'          => 'nullable|numeric|min:0',
            'start_date'      => 'nullable|date',
            'end_date'        => 'nullable|date',
            'status'          => 'nullable|in:draft,active,at_risk,behind,completed,cancelled',
        ]);

        $objective->update($validated);

        return response()->json($objective->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $objective = $this->objectiveInTenant($id, $this->tenantId($request));

        $this->authorize('delete', $objective);

        $objective->delete();

        return response()->json(['message' => 'Objective deleted.']);
    }

    public function cascade(Request $request, int $id): JsonResponse
    {
        $this->authorize('create', StrategyObjective::class);

        // Chantier 19 (Lot 5): verify the parent objective is the caller's
        // own before cascading a child under it — previously any user could
        // attach a child objective under another company's parent by id.
        $this->objectiveInTenant($id, $this->tenantId($request));

        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'level'      => 'required|in:vision,mission,strategic,annual,quarterly,team,individual',
            'owner_type' => 'nullable|in:company,department,team,user',
            'owner_id'   => 'nullable|integer',
        ]);

        $child = $this->service->cascadeObjective($id, $validated);

        return response()->json($child, 201);
    }

    public function tree(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->user()?->company_id ?? 0);
        $planId   = $request->filled('plan_id') ? $request->integer('plan_id') : null;

        $tree = $this->service->getOkrTree($tenantId, $planId);

        return response()->json($tree);
    }

    public function storeKr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'objective_id'       => 'required|exists:strategy_objectives,id',
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'type'               => 'nullable|in:percentage,number,currency,boolean,milestone',
            'baseline_value'     => 'nullable|numeric',
            'target_value'       => 'required|numeric',
            'current_value'      => 'nullable|numeric',
            'unit'               => 'nullable|string|max:50',
            'data_source_module' => 'nullable|string|max:100',
            'data_source_key'    => 'nullable|string|max:100',
        ]);

        // Chantier 19 (Lot 5): without this, any user could attach a key
        // result to another company's objective by id.
        $this->objectiveInTenant($validated['objective_id'], $this->tenantId($request));

        $kr = $this->service->addKeyResult($validated['objective_id'], $validated);

        return response()->json($kr, 201);
    }

    public function updateKr(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'target_value'   => 'sometimes|numeric',
            'unit'           => 'nullable|string|max:50',
            'confidence'     => 'nullable|in:on_track,at_risk,behind',
        ]);

        $kr = StrategyKeyResult::with('objective')->findOrFail($id);
        $this->objectiveInTenant($kr->objective_id, $this->tenantId($request));
        $kr->update($validated);

        return response()->json($kr->fresh());
    }

    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'current_value' => 'required|numeric',
        ]);

        // Chantier 19 (Lot 5): verify the key result's objective is the
        // caller's own before letting them move its progress.
        $kr = StrategyKeyResult::findOrFail($id);
        $this->objectiveInTenant($kr->objective_id, $this->tenantId($request));

        $kr = $this->service->updateKeyResultProgress($id, $request->float('current_value'));

        return response()->json($kr);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }

    /**
     * Load an objective and 404 unless it belongs to the caller's own
     * tenant (via its plan) — StrategyObjective has no tenant_id column of
     * its own, and StrategyObjectivePolicy::canManage() only checks role,
     * never ownership, so every mutating action needs this explicit check.
     */
    private function objectiveInTenant(int $objectiveId, string $tenantId): StrategyObjective
    {
        $objective = StrategyObjective::with('plan')->findOrFail($objectiveId);

        abort_if((string) ($objective->plan?->tenant_id ?? '') !== $tenantId, 404);

        return $objective;
    }
}
