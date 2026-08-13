<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workflow\Jobs\ExecuteWorkflowJob;
use Modules\Workflow\Models\WorkflowChainDefinition;
use Modules\Workflow\Models\WorkflowChainExecution;
use Modules\Workflow\Services\WorkflowEngineService;

/**
 * @group Workflow Chain (Phase 39)
 *
 * CRM→Sales→Manufacturing trigger-based automation engine.
 */
class WorkflowChainController extends Controller
{
    public function __construct(
        private readonly WorkflowEngineService $engine,
    ) {}

    // ── Definitions ───────────────────────────────────────────────────────────

    /**
     * List all workflow chain definitions for the current tenant.
     *
     * @queryParam trigger_module string Filter by module (CRM, Sales, Manufacturing…)
     * @queryParam is_active boolean Filter by active status
     * @queryParam per_page int Results per page (max 100). Default: 20
     */
    public function indexDefinitions(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->per_page ?? 20), 100);

        $query = WorkflowChainDefinition::forTenant($tenantId)
            ->withCount('executions')
            ->when(
                $request->filled('trigger_module'),
                fn ($q) => $q->forModule($request->string('trigger_module')->toString())
            )
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->latest();

        return response()->json($query->paginate($perPage));
    }

    /**
     * Create a new workflow chain definition.
     */
    public function storeDefinition(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string|max:2000',
            'trigger_key'    => 'required|string|max:100',
            'trigger_module' => 'required|string|max:100',
            'conditions'     => 'nullable|array',
            'conditions.*.field'    => 'sometimes|string',
            'conditions.*.operator' => 'required_with:conditions|string',
            'conditions.*.value'    => 'sometimes',
            'actions'        => 'required|array|min:1',
            'actions.*.action_key' => 'required|string',
            'actions.*.params'     => 'nullable|array',
            'actions.*.critical'   => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
        ]);

        $definition = WorkflowChainDefinition::create(array_merge(
            $validated,
            ['tenant_id' => $this->tenantId($request)]
        ));

        return response()->json($definition, 201);
    }

    /**
     * Show a single workflow chain definition.
     */
    public function showDefinition(Request $request, int $id): JsonResponse
    {
        $definition = WorkflowChainDefinition::forTenant($this->tenantId($request))
            ->withCount('executions')
            ->findOrFail($id);

        return response()->json($definition);
    }

    /**
     * Update a workflow chain definition.
     */
    public function updateDefinition(Request $request, int $id): JsonResponse
    {
        $definition = WorkflowChainDefinition::forTenant($this->tenantId($request))->findOrFail($id);

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:200',
            'description'    => 'nullable|string|max:2000',
            'trigger_key'    => 'sometimes|string|max:100',
            'trigger_module' => 'sometimes|string|max:100',
            'conditions'     => 'nullable|array',
            'actions'        => 'sometimes|array|min:1',
            'actions.*.action_key' => 'required_with:actions|string',
            'actions.*.params'     => 'nullable|array',
            'actions.*.critical'   => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
        ]);

        $definition->update($validated);

        return response()->json($definition->fresh());
    }

    /**
     * Soft-delete (deactivate) a workflow chain definition.
     */
    public function destroyDefinition(Request $request, int $id): JsonResponse
    {
        $definition = WorkflowChainDefinition::forTenant($this->tenantId($request))->findOrFail($id);
        $definition->delete();

        return response()->json(['message' => 'Workflow supprimé.']);
    }

    /**
     * List executions for a specific definition.
     */
    public function definitionExecutions(Request $request, int $id): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $definition = WorkflowChainDefinition::forTenant($tenantId)->findOrFail($id);
        $perPage    = min((int) ($request->per_page ?? 25), 100);

        $executions = $definition->executions()
            ->forTenant($tenantId)
            ->latest()
            ->paginate($perPage);

        return response()->json($executions);
    }

    // ── Executions ────────────────────────────────────────────────────────────

    /**
     * List all executions for the current tenant (paginated).
     *
     * @queryParam status string Filter by status (pending|running|completed|failed)
     * @queryParam trigger_key string Filter by trigger key
     * @queryParam date_from date Filter executions on/after this date
     * @queryParam date_to date Filter executions on/before this date
     */
    public function indexExecutions(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->per_page ?? 25), 100);

        $executions = WorkflowChainExecution::forTenant($tenantId)
            ->with('definition:id,name,trigger_key,trigger_module')
            ->when($request->filled('status'),      fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('trigger_key'), fn ($q) => $q->where('trigger_key', $request->trigger_key))
            ->when($request->filled('date_from'),   fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),     fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate($perPage);

        return response()->json($executions);
    }

    /**
     * Show a single execution with full result log.
     */
    public function showExecution(Request $request, int $id): JsonResponse
    {
        $execution = WorkflowChainExecution::forTenant($this->tenantId($request))
            ->with('definition:id,name,trigger_key,trigger_module')
            ->findOrFail($id);

        return response()->json($execution->append('duration_ms'));
    }

    // ── Manual Trigger ────────────────────────────────────────────────────────

    /**
     * Manually trigger a workflow for testing purposes.
     *
     * @bodyParam trigger_key string required e.g. 'crm.opportunity.won'
     * @bodyParam context array Trigger payload (opportunity_id, amount, etc.)
     * @bodyParam sync boolean Run synchronously (default: false = queued)
     */
    public function manualTrigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trigger_key' => 'required|string|max:100',
            'context'     => 'nullable|array',
            'sync'        => 'nullable|boolean',
        ]);

        $context = array_merge(
            $validated['context'] ?? [],
            ['tenant_id' => $this->tenantId($request)]
        );

        $triggerKey = $validated['trigger_key'];
        $sync       = (bool) ($validated['sync'] ?? false);

        if ($sync) {
            $results = $this->engine->executeWorkflow($triggerKey, $context);
            return response()->json(['mode' => 'sync', 'results' => $results]);
        }

        ExecuteWorkflowJob::dispatch($triggerKey, $context);

        return response()->json([
            'mode'        => 'async',
            'trigger_key' => $triggerKey,
            'message'     => 'Workflow déclenché en arrière-plan.',
        ], 202);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    /**
     * Aggregate stats for the workflow dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $total   = WorkflowChainDefinition::forTenant($tenantId)->count();
        $active  = WorkflowChainDefinition::forTenant($tenantId)->active()->count();

        $today = WorkflowChainExecution::forTenant($tenantId)->today();

        $executionsToday = (clone $today)->count();
        $successToday    = (clone $today)->completed()->count();
        $failedToday     = (clone $today)->failed()->count();

        $successRate = $executionsToday > 0
            ? round($successToday / $executionsToday * 100, 1)
            : 100.0;

        return response()->json([
            'total_definitions'  => $total,
            'active_definitions' => $active,
            'executions_today'   => $executionsToday,
            'success_today'      => $successToday,
            'failed_today'       => $failedToday,
            'success_rate'       => $successRate,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->tenant_id ?? $request->user()->id ?? 1);
    }
}
