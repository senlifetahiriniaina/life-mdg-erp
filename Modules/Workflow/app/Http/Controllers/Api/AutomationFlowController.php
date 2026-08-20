<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Modules\Workflow\Models\Automation\AutomationExecution;
use Modules\Workflow\Models\Automation\AutomationFlow;
use Modules\Workflow\Models\Automation\AutomationFlowTemplate;
use Modules\Workflow\Models\Automation\AutomationNode;
use Modules\Workflow\Services\Automation\FlowExecutionEngine;
use Modules\Workflow\Services\Automation\NodeTypeRegistry;

/**
 * AutomationFlowController
 *
 * Handles all n8n-like automation API endpoints:
 *
 *   GET    /api/v1/automation/flows
 *   POST   /api/v1/automation/flows
 *   GET    /api/v1/automation/flows/{id}
 *   PUT    /api/v1/automation/flows/{id}
 *   DELETE /api/v1/automation/flows/{id}
 *   POST   /api/v1/automation/flows/{id}/activate
 *   POST   /api/v1/automation/flows/{id}/deactivate
 *   POST   /api/v1/automation/flows/{id}/execute
 *   GET    /api/v1/automation/flows/{id}/executions
 *   GET    /api/v1/automation/executions
 *   GET    /api/v1/automation/executions/{id}
 *   GET    /api/v1/automation/node-types
 *   GET    /api/v1/automation/templates
 *   POST   /api/v1/automation/flows/from-template
 *   POST   /api/v1/automation/webhook/{uuid}
 */
class AutomationFlowController extends Controller
{
    public function __construct(
        private readonly NodeTypeRegistry   $registry,
        private readonly FlowExecutionEngine $engine,
    ) {}

    // ── Flow CRUD ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/automation/flows
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $query = AutomationFlow::forTenant($tenantId)
            ->with(['nodes', 'variables'])
            ->orderByDesc('updated_at');

        if ($request->has('active')) {
            $query->active();
        }

        if ($triggerType = $request->input('trigger_type')) {
            $query->byTriggerType($triggerType);
        }

        if ($search = $request->input('q')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $flows = $query->paginate((int) ($request->input('per_page', 20)));

        return response()->json($flows);
    }

    /**
     * POST /api/v1/automation/flows
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'icon'           => 'nullable|string|max:10',
            'color'          => 'nullable|string|max:9',
            'trigger_type'   => 'required|in:webhook,schedule,module_event,manual',
            'trigger_config' => 'nullable|array',
            'tags'           => 'nullable|array',
            'nodes'          => 'nullable|array',
            'connections'    => 'nullable|array',
            'variables'      => 'nullable|array',
        ]);

        $tenantId = $this->tenantId($request);

        $flow = AutomationFlow::create([
            'tenant_id'      => $tenantId,
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'icon'           => $validated['icon'] ?? '⚙️',
            'color'          => $validated['color'] ?? '#6366F1',
            'is_active'      => false,
            'trigger_type'   => $validated['trigger_type'],
            'trigger_config' => $validated['trigger_config'] ?? null,
            'tags'           => $validated['tags'] ?? [],
            'created_by'     => $request->user()?->id,
        ]);

        // Create nodes
        $nodeIdMap = [];
        foreach ($validated['nodes'] ?? [] as $nodeDef) {
            $tmpId = $nodeDef['_template_id'] ?? null;
            $node  = $this->createNode($flow->id, $nodeDef);
            if ($tmpId) {
                $nodeIdMap[$tmpId] = $node->id;
            }
        }

        // Create connections
        foreach ($validated['connections'] ?? [] as $connDef) {
            $this->createConnection($flow->id, $connDef, $nodeIdMap);
        }

        // Create variables
        foreach ($validated['variables'] ?? [] as $varDef) {
            $flow->variables()->create([
                'name'          => $varDef['name'],
                'value_type'    => $varDef['value_type'] ?? 'string',
                'default_value' => $varDef['default_value'] ?? null,
                'description'   => $varDef['description'] ?? null,
            ]);
        }

        return response()->json($flow->load(['nodes', 'connections', 'variables']), 201);
    }

    /**
     * GET /api/v1/automation/flows/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $flow = $this->findFlow($request, $id);
        return response()->json($flow->load(['nodes', 'connections', 'variables']));
    }

    /**
     * PUT /api/v1/automation/flows/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $flow = $this->findFlow($request, $id);

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'icon'           => 'nullable|string|max:10',
            'color'          => 'nullable|string|max:9',
            'trigger_type'   => 'sometimes|in:webhook,schedule,module_event,manual',
            'trigger_config' => 'nullable|array',
            'tags'           => 'nullable|array',
        ]);

        $flow->update($validated);
        $flow->increment('version');

        return response()->json($flow->load(['nodes', 'connections', 'variables']));
    }

    /**
     * DELETE /api/v1/automation/flows/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $flow = $this->findFlow($request, $id);
        $flow->delete();
        return response()->json(['deleted' => true]);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/automation/flows/{id}/activate
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $flow = $this->findFlow($request, $id);
        $flow->update(['is_active' => true]);
        return response()->json(['is_active' => true, 'id' => $flow->id]);
    }

    /**
     * POST /api/v1/automation/flows/{id}/deactivate
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $flow = $this->findFlow($request, $id);
        $flow->update(['is_active' => false]);
        return response()->json(['is_active' => false, 'id' => $flow->id]);
    }

    /**
     * POST /api/v1/automation/flows/{id}/execute   — manual trigger
     */
    public function execute(Request $request, int $id): JsonResponse
    {
        $flow = $this->findFlow($request, $id);

        $triggerData = $request->input('trigger_data', []);
        $triggerData['_manual_trigger'] = true;
        $triggerData['_triggered_by']   = $request->user()?->id;

        $execution = $this->engine->execute($flow, $triggerData);

        return response()->json($execution, 201);
    }

    /**
     * GET /api/v1/automation/flows/{id}/executions
     */
    public function flowExecutions(Request $request, int $id): JsonResponse
    {
        $flow       = $this->findFlow($request, $id);
        $executions = $flow->executions()
            ->orderByDesc('started_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json($executions);
    }

    // ── Executions ────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/automation/executions
     */
    public function indexExecutions(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $query = AutomationExecution::forTenant($tenantId)
            ->with('flow')
            ->orderByDesc('started_at');

        if ($status = $request->input('status')) {
            $query->withStatus($status);
        }

        if ($flowId = $request->input('flow_id')) {
            $query->where('flow_id', (int) $flowId);
        }

        $executions = $query->paginate((int) $request->input('per_page', 20));

        return response()->json($executions);
    }

    /**
     * GET /api/v1/automation/executions/{id}
     */
    public function showExecution(Request $request, int $id): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $execution = AutomationExecution::forTenant($tenantId)
            ->with('flow.nodes')
            ->findOrFail($id);

        return response()->json($execution);
    }

    /**
     * POST /api/v1/automation/executions/{id}/retry
     */
    public function retryExecution(Request $request, int $id): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $execution = AutomationExecution::forTenant($tenantId)->findOrFail($id);
        $newExec   = $this->engine->retry($execution);
        return response()->json($newExec, 201);
    }

    /**
     * POST /api/v1/automation/executions/{id}/pause
     */
    public function pauseExecution(Request $request, int $id): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $execution = AutomationExecution::forTenant($tenantId)->findOrFail($id);
        $this->engine->pause($execution);
        return response()->json(['status' => 'paused']);
    }

    /**
     * POST /api/v1/automation/executions/{id}/resume
     */
    public function resumeExecution(Request $request, int $id): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $execution = AutomationExecution::forTenant($tenantId)->findOrFail($id);
        $this->engine->resume($execution);
        return response()->json(['status' => 'running']);
    }

    // ── Node Types & Templates ────────────────────────────────────────────────────

    /**
     * GET /api/v1/automation/node-types
     */
    public function nodeTypes(Request $request): JsonResponse
    {
        $all = $this->registry->getAll();

        if ($module = $request->input('module')) {
            $all = $this->registry->getByModule($module);
        }

        if ($category = $request->input('category')) {
            $all = $this->registry->getByCategory($category);
        }

        // Group by category for easier frontend consumption
        $grouped = [];
        foreach ($all as $def) {
            $grouped[$def['category']][] = $def;
        }

        return response()->json([
            'total'   => count($all),
            'grouped' => $grouped,
            'flat'    => array_values($all),
        ]);
    }

    /**
     * GET /api/v1/automation/templates
     */
    public function templates(Request $request): JsonResponse
    {
        $query = AutomationFlowTemplate::builtin()->orderBy('category')->orderBy('name');

        if ($category = $request->input('category')) {
            $query->byCategory($category);
        }

        $templates = $query->get();

        return response()->json($templates);
    }

    /**
     * POST /api/v1/automation/flows/from-template
     */
    public function fromTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|integer|exists:automation_templates,id',
        ]);

        $template = AutomationFlowTemplate::findOrFail($validated['template_id']);
        $tenantId = $this->tenantId($request);
        $userId   = $request->user()?->id ?? 0;

        $flow = $template->instantiateForTenant($tenantId, $userId);

        return response()->json($flow->load(['nodes', 'connections', 'variables']), 201);
    }

    // ── Webhook Inbound Trigger ───────────────────────────────────────────────────

    /**
     * POST /api/v1/automation/webhook/{uuid}
     *
     * Public endpoint — no auth:sanctum middleware required on this route.
     * The uuid is stored in automation_flows.trigger_config->uuid.
     */
    public function webhookTrigger(Request $request, string $uuid): JsonResponse
    {
        $flow = AutomationFlow::active()
            ->where('trigger_type', 'webhook')
            ->whereJsonContains('trigger_config->uuid', $uuid)
            ->first();

        if (!$flow) {
            return response()->json(['error' => 'Webhook not found or flow inactive.'], 404);
        }

        // Optional HMAC signature verification
        $secret = $flow->trigger_config['secret'] ?? null;
        if ($secret) {
            $signature = $request->header('X-Widehalo-Signature');
            $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($expected, (string) $signature)) {
                return response()->json(['error' => 'Invalid webhook signature.'], 401);
            }
        }

        $triggerData = [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'method'  => $request->method(),
            'uuid'    => $uuid,
        ];

        $execution = $this->engine->execute($flow, $triggerData);

        return response()->json([
            'received'     => true,
            'execution_id' => $execution->id,
            'status'       => $execution->status,
        ], 202);
    }

    // ── Node & Connection Management ─────────────────────────────────────────────

    /**
     * POST /api/v1/automation/flows/{id}/nodes
     */
    public function addNode(Request $request, int $id): JsonResponse
    {
        $flow      = $this->findFlow($request, $id);
        $validated = $request->validate([
            'node_type'      => 'required|string',
            'node_key'       => 'required|string',
            'label'          => 'required|string',
            'position_x'     => 'nullable|integer',
            'position_y'     => 'nullable|integer',
            'config'         => 'nullable|array',
            'error_handling' => 'nullable|in:skip,retry,stop,notify',
        ]);

        // Validate node key exists in registry
        if (!$this->registry->has($validated['node_key'])) {
            return response()->json(['error' => "Unknown node key: '{$validated['node_key']}'"], 422);
        }

        $typeDef = $this->registry->resolve($validated['node_key']);
        $node    = $this->createNode($flow->id, array_merge($validated, [
            'input_schema'  => $typeDef['input_schema'],
            'output_schema' => $typeDef['output_schema'],
        ]));

        return response()->json($node, 201);
    }

    /**
     * PUT /api/v1/automation/flows/{flowId}/nodes/{nodeId}
     */
    public function updateNode(Request $request, int $flowId, int $nodeId): JsonResponse
    {
        $flow = $this->findFlow($request, $flowId);
        $node = AutomationNode::where('flow_id', $flow->id)->findOrFail($nodeId);

        $validated = $request->validate([
            'label'          => 'sometimes|string',
            'position_x'     => 'nullable|integer',
            'position_y'     => 'nullable|integer',
            'config'         => 'nullable|array',
            'error_handling' => 'nullable|in:skip,retry,stop,notify',
        ]);

        $node->update($validated);
        return response()->json($node);
    }

    /**
     * DELETE /api/v1/automation/flows/{flowId}/nodes/{nodeId}
     */
    public function removeNode(Request $request, int $flowId, int $nodeId): JsonResponse
    {
        $flow = $this->findFlow($request, $flowId);
        $node = AutomationNode::where('flow_id', $flow->id)->findOrFail($nodeId);
        $node->delete();
        return response()->json(['deleted' => true]);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────────

    private function findFlow(Request $request, int $id): AutomationFlow
    {
        return AutomationFlow::forTenant($this->tenantId($request))->findOrFail($id);
    }

    /**
     * Chantier 19 Lot 3: was `$request->user()?->tenant_id ?? $request->header('X-Tenant-ID', 1)`
     * — the phantom `users.tenant_id` column (never populated) meant the
     * fully client-controlled `X-Tenant-ID` header was reached on every
     * real request, the same header-based IDOR pattern already fixed
     * repeatedly elsewhere this session (Setup's original 8.5sv fix,
     * Integration's IntegrationConnectorPolicy, Projects'
     * ProjectAdvancedController::store()). This controller currently has
     * zero routes registered anywhere (see the module-scoped note in
     * routes/api.php) so the bug is dormant, not live — fixed anyway so it
     * isn't a landmine the moment a future chantier wires this controller
     * up, matching this session's established "close the landmine before
     * it's tripped" precedent.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }

    private function createNode(int $flowId, array $def): AutomationNode
    {
        return AutomationNode::create([
            'flow_id'        => $flowId,
            'node_type'      => $def['node_type'],
            'node_key'       => $def['node_key'],
            'label'          => $def['label'],
            'position_x'     => $def['position_x'] ?? 0,
            'position_y'     => $def['position_y'] ?? 0,
            'config'         => $def['config'] ?? null,
            'input_schema'   => $def['input_schema'] ?? null,
            'output_schema'  => $def['output_schema'] ?? null,
            'error_handling' => $def['error_handling'] ?? 'stop',
        ]);
    }

    private function createConnection(int $flowId, array $def, array $nodeIdMap): void
    {
        $sourceId = isset($def['source_template_id'])
            ? ($nodeIdMap[$def['source_template_id']] ?? null)
            : ($def['source_node_id'] ?? null);

        $targetId = isset($def['target_template_id'])
            ? ($nodeIdMap[$def['target_template_id']] ?? null)
            : ($def['target_node_id'] ?? null);

        if (!$sourceId || !$targetId) {
            return;
        }

        \Modules\Workflow\Models\Automation\AutomationConnection::create([
            'flow_id'        => $flowId,
            'source_node_id' => $sourceId,
            'target_node_id' => $targetId,
            'condition_type' => $def['condition_type'] ?? 'always',
            'condition_expr' => $def['condition_expr'] ?? null,
        ]);
    }
}
