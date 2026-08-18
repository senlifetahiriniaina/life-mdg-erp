<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyKeyResult;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Services\OkrService;

class OkrController extends Controller
{
    public function __construct(private OkrService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = StrategyObjective::with(['keyResults', 'pillar']);

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

        $objective = $this->service->createObjective($validated);

        return response()->json($objective, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $objective = StrategyObjective::findOrFail($id);

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

    public function destroy(int $id): JsonResponse
    {
        $objective = StrategyObjective::findOrFail($id);

        $this->authorize('delete', $objective);

        $objective->delete();

        return response()->json(['message' => 'Objective deleted.']);
    }

    public function cascade(Request $request, int $id): JsonResponse
    {
        $this->authorize('create', StrategyObjective::class);

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

        $kr = StrategyKeyResult::findOrFail($id);
        $kr->update($validated);

        return response()->json($kr->fresh());
    }

    public function updateProgress(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'current_value' => 'required|numeric',
        ]);

        $kr = $this->service->updateKeyResultProgress($id, $request->float('current_value'));

        return response()->json($kr);
    }
}
