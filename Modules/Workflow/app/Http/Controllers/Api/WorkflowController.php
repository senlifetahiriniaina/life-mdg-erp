<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Workflow\Exceptions\WorkflowDslParseException;
use Modules\Workflow\Models\WorkflowDefinition;
use Modules\Workflow\Models\WorkflowExecution;
use Modules\Workflow\Services\WorkflowDslParser;
use Modules\Workflow\Services\WorkflowJsonSchema;

/**
 * @group Workflow
 *
 * Workflow definitions, actions, and execution management.
 */
class WorkflowController extends Controller
{
    // ─── Definitions ───────────────────────────────────────────────────────────

    /**
     * List workflow definitions for the current tenant.
     *
     * @queryParam module string Filter by module. Example: CRM
     * @queryParam is_active boolean Filter by active status. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->per_page ?? 20), 100);

        $definitions = WorkflowDefinition::forTenant($tenantId)
            ->when($request->filled('module'), fn ($q) => $q->forModule($request->string('module')->toString()))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)))
            ->with('actions')
            ->latest()
            ->paginate($perPage);

        return response()->json($definitions);
    }

    /**
     * Create a workflow definition with its actions.
     *
     * @bodyParam name string required Workflow name. Example: New Lead Notification
     * @bodyParam module string required Module this workflow belongs to. Example: CRM
     * @bodyParam trigger_event string required Event that triggers the workflow. Example: contact.created
     * @bodyParam description string Optional description.
     * @bodyParam trigger_conditions array Optional trigger condition rules.
     * @bodyParam is_active boolean Whether the workflow is active. Example: true
     * @bodyParam actions array List of actions ({action_type, order, action_config}).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:200',
            'description'        => 'nullable|string|max:2000',
            'module'             => 'required|string|max:100',
            'trigger_event'      => 'required|string|max:200',
            'trigger_conditions' => 'nullable|array',
            'is_active'          => 'nullable|boolean',
            'actions'            => 'nullable|array',
            'actions.*.action_type' => 'required_with:actions|in:send_email,send_sms,update_field,create_record,webhook,assign_user',
            'actions.*.order'       => 'nullable|integer|min:0',
            'actions.*.action_config' => 'nullable|array',
        ]);

        $tenantId = $this->tenantId($request);

        $definition = WorkflowDefinition::create([
            'tenant_id'          => $tenantId,
            'name'               => $validated['name'],
            'description'        => $validated['description'] ?? null,
            'module'             => $validated['module'],
            'trigger_event'      => $validated['trigger_event'],
            'trigger_conditions' => $validated['trigger_conditions'] ?? null,
            'is_active'          => $validated['is_active'] ?? true,
            'created_by'         => $request->user()->id,
        ]);

        if (! empty($validated['actions'])) {
            foreach ($validated['actions'] as $index => $actionData) {
                $definition->actions()->create([
                    'action_type'   => $actionData['action_type'],
                    'order'         => $actionData['order'] ?? $index,
                    'action_config' => $actionData['action_config'] ?? null,
                ]);
            }
        }

        return response()->json($definition->load('actions'), 201);
    }

    /**
     * Show a single workflow definition with its actions.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $definition = WorkflowDefinition::forTenant($tenantId)
            ->with('actions')
            ->findOrFail($id);

        return response()->json($definition);
    }

    /**
     * Update a workflow definition (and optionally replace its actions).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $definition = WorkflowDefinition::forTenant($tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name'               => 'sometimes|string|max:200',
            'description'        => 'nullable|string|max:2000',
            'module'             => 'sometimes|string|max:100',
            'trigger_event'      => 'sometimes|string|max:200',
            'trigger_conditions' => 'nullable|array',
            'is_active'          => 'nullable|boolean',
            'actions'            => 'nullable|array',
            'actions.*.action_type' => 'required_with:actions|in:send_email,send_sms,update_field,create_record,webhook,assign_user',
            'actions.*.order'       => 'nullable|integer|min:0',
            'actions.*.action_config' => 'nullable|array',
        ]);

        $definition->update($validated);

        if (array_key_exists('actions', $validated) && is_array($validated['actions'])) {
            $definition->actions()->delete();
            foreach ($validated['actions'] as $index => $actionData) {
                $definition->actions()->create([
                    'action_type'   => $actionData['action_type'],
                    'order'         => $actionData['order'] ?? $index,
                    'action_config' => $actionData['action_config'] ?? null,
                ]);
            }
        }

        return response()->json($definition->load('actions'));
    }

    /**
     * Deactivate (soft-delete) a workflow definition.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $definition = WorkflowDefinition::forTenant($tenantId)->findOrFail($id);
        $definition->delete();

        return response()->json(['message' => 'Workflow deleted.']);
    }

    /**
     * Toggle the active/inactive status of a workflow.
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $definition = WorkflowDefinition::forTenant($tenantId)->findOrFail($id);

        $definition->update(['is_active' => ! $definition->is_active]);

        return response()->json([
            'id'        => $definition->id,
            'is_active' => $definition->is_active,
        ]);
    }

    // ─── Executions ────────────────────────────────────────────────────────────

    /**
     * List execution history for a workflow (paginated).
     *
     * @queryParam per_page integer Results per page (max 100). Example: 20
     */
    public function executions(Request $request, int $id): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $definition = WorkflowDefinition::forTenant($tenantId)->findOrFail($id);
        $perPage    = min((int) ($request->per_page ?? 20), 100);

        $executions = $definition->executions()
            ->forTenant($tenantId)
            ->with('logs')
            ->paginate($perPage);

        return response()->json($executions);
    }

    /**
     * Manually trigger a workflow execution.
     *
     * @bodyParam workflow_id int required The workflow to trigger. Example: 1
     * @bodyParam context array Optional context data passed as trigger_data.
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workflow_id' => 'required|integer',
            'context'     => 'nullable|array',
        ]);

        $tenantId   = $this->tenantId($request);
        $definition = WorkflowDefinition::forTenant($tenantId)
            ->findOrFail($validated['workflow_id']);

        $execution = WorkflowExecution::create([
            'tenant_id'    => $tenantId,
            'workflow_id'  => $definition->id,
            'trigger_data' => $validated['context'] ?? null,
            'status'       => 'pending',
            'started_at'   => now(),
        ]);

        // Simulate execution: run each action and log results.
        $execution->update(['status' => 'running']);

        $allSucceeded = true;
        $actions = $definition->actions()->get();
        foreach ($actions as $action) {
            $execution->logs()->create([
                'action_id'   => $action->id,
                'status'      => 'completed',
                'result'      => ['action_type' => $action->action_type, 'executed' => true],
                'executed_at' => now(),
            ]);
        }

        $execution->update([
            'status'       => $allSucceeded ? 'completed' : 'failed',
            'completed_at' => now(),
        ]);

        return response()->json($execution->load('logs'), 201);
    }

    // ─── DSL Endpoints ────────────────────────────────────────────────────────

    /**
     * Parse DSL text and return the equivalent JSON workflow definition.
     *
     * @bodyParam dsl string required DSL text (one or more rules). Example: SI montant > 500000 ALORS approuver_3_niveaux
     */
    public function dslParse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dsl' => 'required|string|max:10000',
        ]);

        $parser = app(WorkflowDslParser::class);

        try {
            $definitions = $parser->parse($validated['dsl']);

            return response()->json([
                'success'     => true,
                'definitions' => $definitions,
                'count'       => count($definitions),
            ]);
        } catch (WorkflowDslParseException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->toArray(),
            ], 422);
        }
    }

    /**
     * Validate DSL text without creating a workflow.
     *
     * @bodyParam dsl string required DSL text to validate. Example: QUAND opportunite_gagnee SI montant > 0 ALORS creer_commande
     */
    public function dslValidate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dsl' => 'required|string|max:10000',
        ]);

        $parser = app(WorkflowDslParser::class);
        $result = $parser->validateFull($validated['dsl']);

        return response()->json($result, $result['valid'] ? 200 : 422);
    }

    /**
     * Parse DSL text and immediately create workflow definitions.
     *
     * @bodyParam dsl string required DSL text. Example: QUAND facture_recue SI montant > 100000 ALORS approbation_1_niveau
     * @bodyParam name_prefix string Optional prefix for workflow names.
     */
    public function dslCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dsl'         => 'required|string|max:10000',
            'name_prefix' => 'nullable|string|max:100',
        ]);

        $parser   = app(WorkflowDslParser::class);
        $tenantId = $this->tenantId($request);
        $prefix   = $validated['name_prefix'] ?? 'Règle DSL';
        $created  = [];

        try {
            $definitions = $parser->parse($validated['dsl']);

            foreach ($definitions as $index => $def) {
                $name       = $prefix . ' #' . ($index + 1);
                $definition = WorkflowDefinition::create([
                    'tenant_id'          => $tenantId,
                    'name'               => $name,
                    'module'             => $this->moduleFromTrigger($def['trigger_key'] ?? ''),
                    'trigger_event'      => $def['trigger_key'] ?? 'generic.condition_check',
                    'trigger_conditions' => [
                        'conditions'  => $def['conditions'] ?? [],
                        'logic'       => $def['condition_logic'] ?? 'ET',
                        'else_actions' => $def['else_actions'] ?? [],
                    ],
                    'is_active'  => true,
                    'created_by' => $request->user()->id,
                ]);

                foreach ($def['actions'] as $order => $action) {
                    $definition->actions()->create([
                        'action_type'   => 'workflow_action',
                        'order'         => $order,
                        'action_config' => $action,
                    ]);
                }

                $created[] = $definition->load('actions');
            }

            return response()->json([
                'success' => true,
                'created' => $created,
                'count'   => count($created),
            ], 201);
        } catch (WorkflowDslParseException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->toArray(),
            ], 422);
        }
    }

    // ─── Schema & Catalogue Endpoints ─────────────────────────────────────────

    /**
     * Return the JSON Schema for workflow definitions.
     */
    public function schema(): JsonResponse
    {
        $schemaService = app(WorkflowJsonSchema::class);

        return response()->json([
            'schema'   => $schemaService->getSchema(),
            'examples' => $schemaService->getExamples(),
        ]);
    }

    /**
     * List all available actions with their module grouping and parameter schema.
     */
    public function actions(): JsonResponse
    {
        $grouped = [];
        foreach (WorkflowJsonSchema::ACTIONS as $key => $meta) {
            $module           = $meta['module'];
            $grouped[$module] = $grouped[$module] ?? [];
            $grouped[$module][] = array_merge(['key' => $key], $meta);
        }

        return response()->json([
            'actions' => WorkflowJsonSchema::ACTIONS,
            'grouped' => $grouped,
        ]);
    }

    /**
     * List all available triggers with their module grouping.
     */
    public function triggers(): JsonResponse
    {
        $grouped = [];
        foreach (WorkflowJsonSchema::TRIGGERS as $key => $meta) {
            $module           = $meta['module'];
            $grouped[$module] = $grouped[$module] ?? [];
            $grouped[$module][] = array_merge(['key' => $key], $meta);
        }

        return response()->json([
            'triggers' => WorkflowJsonSchema::TRIGGERS,
            'grouped'  => $grouped,
        ]);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Chantier 10: was `$request->user()->tenant_id ?? $request->user()->id ?? 1`
     * — the phantom tenant_id column, collapsing every tenant's workflow
     * definitions into a shared bucket keyed by whichever user happened to
     * hit the endpoint first (or literal tenant 1). Fixed to the real
     * tenant boundary, company_id.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->company_id ?? 0);
    }

    private function moduleFromTrigger(string $triggerKey): string
    {
        $meta = WorkflowJsonSchema::TRIGGERS[$triggerKey] ?? null;

        return $meta['module'] ?? explode('.', $triggerKey)[0] ?? 'Workflow';
    }
}
