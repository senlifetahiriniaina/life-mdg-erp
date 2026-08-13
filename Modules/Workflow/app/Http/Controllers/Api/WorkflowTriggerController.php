<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workflow\Models\WorkflowDefinition;
use Modules\Workflow\Models\WorkflowExecution;
use Modules\Workflow\Services\Automation\NodeTypeRegistry;
use Modules\Workflow\Services\WorkflowEngineService;

/**
 * @group Workflow - Triggers
 *
 * List trigger types, fire triggers, and view trigger history.
 */
class WorkflowTriggerController extends Controller
{
    public function __construct(
        private NodeTypeRegistry $registry,
        private WorkflowEngineService $engine,
    ) {}

    /**
     * List all available trigger node types.
     *
     * @queryParam module string Filter by module. Example: CRM
     * @queryParam category string Filter by category. Example: trigger
     */
    public function index(Request $request): JsonResponse
    {
        $all = $this->registry->getAll();

        $triggers = collect($all)
            ->filter(fn ($node) => ($node['category'] ?? '') === 'trigger')
            ->when(
                $request->filled('module'),
                fn ($c) => $c->filter(fn ($n) => ($n['module'] ?? '') === $request->module)
            )
            ->values();

        return response()->json(['data' => $triggers]);
    }

    /**
     * Fire a workflow trigger by key.
     *
     * @bodyParam key string required Trigger key. Example: crm.opportunity.won
     * @bodyParam payload array Context data passed to the trigger. Example: {"opportunity_id": 42}
     */
    public function fire(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'     => 'required|string',
            'payload' => 'sometimes|array',
        ]);

        $tenantId = $request->user()?->tenant_id ?? 1;

        $execution = $this->engine->triggerByKey(
            $validated['key'],
            array_merge($validated['payload'] ?? [], ['tenant_id' => $tenantId])
        );

        return response()->json([
            'data'    => $execution,
            'message' => 'Trigger fired successfully',
        ], 201);
    }

    /**
     * Get the execution history for a specific trigger key.
     *
     * @queryParam key string required Trigger key. Example: crm.opportunity.won
     * @queryParam per_page integer Results per page. Example: 20
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate(['key' => 'required|string']);

        $perPage = min((int) ($request->per_page ?? 20), 100);

        $executions = WorkflowExecution::query()
            ->where('trigger_key', $request->key)
            ->latest()
            ->paginate($perPage);

        return response()->json($executions);
    }

    /**
     * Get the list of workflows listening to a specific trigger key.
     */
    public function listeners(Request $request, string $key): JsonResponse
    {
        $tenantId   = $request->user()?->tenant_id ?? 1;
        $workflows  = WorkflowDefinition::forTenant($tenantId)
            ->where('trigger_key', $key)
            ->get();

        return response()->json(['data' => $workflows]);
    }
}
