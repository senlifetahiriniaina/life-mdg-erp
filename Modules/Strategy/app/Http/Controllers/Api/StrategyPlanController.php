<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Services\StrategyPlanService;

class StrategyPlanController extends Controller
{
    public function __construct(private StrategyPlanService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $plans = StrategyPlan::forTenant($tenantId)
            ->withCount('objectives')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StrategyPlan::class);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'vision'       => 'nullable|string',
            'mission'      => 'nullable|string',
            'period_start' => 'required|integer|min:2020|max:2050',
            'period_end'   => 'required|integer|min:2020|max:2050|gte:period_start',
            'framework'    => 'nullable|in:okr,bsc,hoshin,hybrid',
            'status'       => 'nullable|in:draft,active,archived',
        ]);

        $tenantId = $this->tenantId($request);
        $userId   = $request->user()->id;

        $plan = $this->service->createPlan($tenantId, $validated, $userId);

        return response()->json($plan, 201);
    }

    public function show(int $id): JsonResponse
    {
        $plan = StrategyPlan::with(['pillars', 'objectives'])->findOrFail($id);
        $this->authorize('view', $plan);

        return response()->json($plan);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = StrategyPlan::findOrFail($id);
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'vision'       => 'nullable|string',
            'mission'      => 'nullable|string',
            'period_start' => 'sometimes|integer|min:2020|max:2050',
            'period_end'   => 'sometimes|integer|min:2020|max:2050',
            'framework'    => 'nullable|in:okr,bsc,hoshin,hybrid',
            'status'       => 'nullable|in:draft,active,archived',
        ]);

        $plan = $this->service->updatePlan($id, $validated);

        return response()->json($plan);
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = StrategyPlan::findOrFail($id);
        $this->authorize('delete', $plan);
        $plan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }

    public function tree(Request $request, int $id): JsonResponse
    {
        $plan = $this->planInTenant($id, $this->tenantId($request));

        $tree = $this->service->getFullTree($plan->id);

        return response()->json($tree);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $this->authorize('create', StrategyPlan::class);
        $request->validate(['name' => 'required|string|max:255']);

        // Chantier 32.27: previously duplicated ANY plan by id regardless of
        // owning company — confirmed empirically that Company A could
        // duplicate Company B's real plan (name/vision/mission/pillars/
        // objectives all copied into the response body, a real data leak
        // even though the copy's tenant_id stayed on the original company).
        $plan = $this->planInTenant($id, $this->tenantId($request));

        $duplicated = $this->service->duplicatePlan($plan->id, $request->input('name'));

        return response()->json($duplicated, 201);
    }

    public function health(Request $request, int $id): JsonResponse
    {
        $plan = $this->planInTenant($id, $this->tenantId($request), with: 'objectives');
        $this->authorize('view', $plan);

        $score = $this->service->computeHealthScore($plan);

        $plan->update(['health_score' => $score]);

        return response()->json([
            'plan_id'      => $id,
            'health_score' => $score,
            'breakdown'    => [
                'total_objectives'    => $plan->objectives->count(),
                'active_objectives'   => $plan->objectives->where('status', 'active')->count(),
                'at_risk_objectives'  => $plan->objectives->where('status', 'at_risk')->count(),
                'behind_objectives'   => $plan->objectives->where('status', 'behind')->count(),
                'completed_objectives' => $plan->objectives->where('status', 'completed')->count(),
                'avg_progress'        => round($plan->objectives->avg('progress') ?? 0, 2),
            ],
        ]);
    }

    /**
     * Load a plan and 404 unless it belongs to the caller's own tenant —
     * mirrors OkrController::objectiveInTenant()'s established shape.
     */
    private function planInTenant(int $id, string $tenantId, ?string $with = null): StrategyPlan
    {
        $query = $with ? StrategyPlan::with($with) : StrategyPlan::query();
        $plan  = $query->findOrFail($id);

        abort_if((string) ($plan->tenant_id ?? '') !== $tenantId, 404);

        return $plan;
    }

    /**
     * Chantier 10 (Strategy): a Chantier 8.6 pass had already removed the
     * client-controlled X-Tenant-Id header/query-param fallback (good), but
     * replaced it with `$request->user()?->tenant_id ?? 'default'` — despite
     * its own comment's claim, `users.tenant_id` is the phantom column
     * documented repeatedly elsewhere in CLAUDE.md (real DB column, never in
     * User::$fillable, never populated by any real registration/onboarding
     * path), not the real tenant boundary. Since it's null for virtually
     * every real user, every company's plans/KPIs/ratios/alerts/signals/
     * rituals/scenarios/OKRs/cascade map still silently collapsed into one
     * shared 'default'-tenant bucket — the identical cross-tenant leak this
     * method's own docblock claimed to have fixed, just without the
     * attacker-chosen-victim header syntax. Fixed for real to
     * `$request->user()?->company_id ?? 0` — the actual multi-tenant
     * boundary column used correctly everywhere else in this app (Reporting,
     * Sales, AI, Achats, Integration, Workflow).
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }
}
