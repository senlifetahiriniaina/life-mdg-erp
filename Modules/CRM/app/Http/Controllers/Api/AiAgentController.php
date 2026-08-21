<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\AiAgent;
use Modules\CRM\Services\AiAgentService;

/**
 * @group Controllers - Ai Agent
 *
 * Manage Ai Agent resources.
 */
/**
 * Chantier 32.15 (CRM 14-layer audit): this whole controller had zero authorize()/tenant-
 * scoping calls anywhere — any authenticated CRM-module user of any company could list/read/
 * update/delete every other company's automation agents, and — the more severe finding —
 * run() any other company's agent against an arbitrary entity_type/entity_id, a real
 * cross-tenant write vector (action_type update_field/assign_owner/score_lead mutate the
 * referenced record). crm_ai_agents already carried a real `tenant_id` column, just never
 * populated/filtered — fixed alongside a new AiAgentPolicy.
 */
class AiAgentController extends Controller
{
    public function __construct(private readonly AiAgentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiAgent::class);

        $agents = AiAgent::where('tenant_id', $request->user()->company_id)
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', (bool) $request->is_active)
            )
            ->when(
                $request->trigger_type,
                fn ($q, $v) => $q->where('trigger_type', $v)
            )
            ->latest()
            ->paginate(min((int) ($request->per_page ?? 25), 100));

        return response()->json($agents);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AiAgent::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['required', 'in:schedule,event,manual'],
            'trigger_config' => ['nullable', 'array'],
            'action_type' => ['required', 'in:send_email,create_task,update_field,add_note,score_lead,assign_owner'],
            'action_config' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['tenant_id'] = $request->user()->company_id;

        $agent = $this->service->createAgent($validated);

        return response()->json($agent, 201);
    }

    public function show(AiAgent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        return response()->json($agent);
    }

    public function update(Request $request, AiAgent $agent): JsonResponse
    {
        $this->authorize('update', $agent);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['sometimes', 'in:schedule,event,manual'],
            'trigger_config' => ['nullable', 'array'],
            'action_type' => ['sometimes', 'in:send_email,create_task,update_field,add_note,score_lead,assign_owner'],
            'action_config' => ['nullable', 'array'],
            'conditions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $agent->update($validated);

        return response()->json($agent->fresh());
    }

    public function destroy(AiAgent $agent): JsonResponse
    {
        $this->authorize('delete', $agent);

        $this->service->deleteAgent($agent);

        return response()->json(null, 204);
    }

    public function run(Request $request, AiAgent $agent): JsonResponse
    {
        $this->authorize('run', $agent);

        $validated = $request->validate([
            'entity_type' => ['required', 'string'],
            'entity_id' => ['required', 'integer'],
        ]);

        $run = $this->service->runAgent($agent, $validated['entity_type'], (int) $validated['entity_id']);

        return response()->json($run);
    }

    public function history(AiAgent $agent, Request $request): JsonResponse
    {
        $this->authorize('view', $agent);

        $limit = (int) ($request->limit ?? 50);
        $history = $this->service->getAgentHistory($agent, $limit);

        return response()->json($history);
    }

    public function stats(AiAgent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        $stats = $this->service->getAgentStats($agent);

        return response()->json($stats);
    }

    public function toggle(AiAgent $agent): JsonResponse
    {
        $this->authorize('update', $agent);

        $updated = $this->service->toggleAgent($agent);

        return response()->json($updated);
    }

    public function scheduledRun(Request $request): JsonResponse
    {
        $runs = $this->service->runScheduledAgents($request->user()->company_id);

        return response()->json([
            'ran' => count($runs),
            'runs' => $runs,
        ]);
    }
}
