<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Workflow\Models\WorkflowChainDefinition;
use Modules\Workflow\Models\WorkflowChainExecution;
use Modules\Workflow\Services\WorkflowEngineService;

/**
 * @group Workflow Chain Definitions
 *
 * CRUD + lifecycle management for workflow chain definitions.
 * Supports filtering by chain (crm_sales | achats_inventory | hr_payroll).
 */
class WorkflowDefinitionController extends Controller
{
    // ─── Chain → trigger_module mapping ───────────────────────────────────────

    /** @var array<string, string[]> */
    private const CHAIN_MODULES = [
        'crm_sales'         => ['CRM', 'Sales', 'Manufacturing'],
        'achats_inventory'  => ['Achats', 'Inventory', 'Accounting'],
        'hr_payroll'        => ['HR', 'Payroll', 'IT'],
    ];

    // ─── Index ─────────────────────────────────────────────────────────────────

    /**
     * List workflow chain definitions.
     *
     * @queryParam chain string Filter by chain: crm_sales | achats_inventory | hr_payroll
     * @queryParam trigger_module string Filter by trigger module. Example: HR
     * @queryParam is_active boolean Filter active/inactive. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->per_page ?? 20), 100);

        $query = WorkflowChainDefinition::where('tenant_id', $tenantId);

        // Chain filter — translates to module filter
        if ($request->filled('chain')) {
            $chain   = $request->string('chain')->toString();
            $modules = self::CHAIN_MODULES[$chain] ?? [];
            if ($modules) {
                $query->whereIn('trigger_module', $modules);
            }
        }

        if ($request->filled('trigger_module')) {
            $query->where('trigger_module', $request->string('trigger_module')->toString());
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $definitions = $query->latest()->paginate($perPage);

        return response()->json($definitions);
    }

    // ─── Store ─────────────────────────────────────────────────────────────────

    /**
     * Create a new workflow chain definition.
     *
     * @bodyParam name string required Workflow name. Example: Congé → Ajustement paie
     * @bodyParam trigger_key string required Event key. Example: hr.leave_approved
     * @bodyParam trigger_module string required Module. Example: HR
     * @bodyParam description string Optional description.
     * @bodyParam conditions array Optional condition rules.
     * @bodyParam actions array required Array of action objects [{action_key, order, params}].
     * @bodyParam is_active boolean Default true.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string|max:2000',
            'trigger_key'    => 'required|string|max:100',
            'trigger_module' => 'required|string|max:100',
            'conditions'     => 'nullable|array',
            'actions'        => 'required|array|min:1',
            'actions.*.action_key' => 'required|string|max:100',
            'actions.*.order'      => 'nullable|integer|min:1',
            'actions.*.params'     => 'nullable|array',
            'is_active'      => 'nullable|boolean',
        ]);

        $tenantId   = $this->tenantId($request);
        $definition = WorkflowChainDefinition::create([
            'tenant_id'      => $tenantId,
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'trigger_key'    => $validated['trigger_key'],
            'trigger_module' => $validated['trigger_module'],
            'conditions'     => $validated['conditions'] ?? null,
            'actions'        => $validated['actions'],
            'is_active'      => $validated['is_active'] ?? true,
        ]);

        return response()->json($definition, 201);
    }

    // ─── Show ──────────────────────────────────────────────────────────────────

    /**
     * Show a single workflow chain definition.
     */
    public function show(Request $request, WorkflowChainDefinition $definition): JsonResponse
    {
        $this->authorizeTenant($request, $definition);

        return response()->json($definition);
    }

    // ─── Update ────────────────────────────────────────────────────────────────

    /**
     * Update a workflow chain definition.
     */
    public function update(Request $request, WorkflowChainDefinition $definition): JsonResponse
    {
        $this->authorizeTenant($request, $definition);

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:200',
            'description'    => 'nullable|string|max:2000',
            'trigger_key'    => 'sometimes|string|max:100',
            'trigger_module' => 'sometimes|string|max:100',
            'conditions'     => 'nullable|array',
            'actions'        => 'sometimes|array|min:1',
            'actions.*.action_key' => 'required_with:actions|string|max:100',
            'actions.*.order'      => 'nullable|integer|min:1',
            'actions.*.params'     => 'nullable|array',
            'is_active'      => 'nullable|boolean',
        ]);

        $definition->update($validated);

        return response()->json($definition->fresh());
    }

    // ─── Destroy ───────────────────────────────────────────────────────────────

    /**
     * Soft-delete a workflow chain definition.
     */
    public function destroy(Request $request, WorkflowChainDefinition $definition): JsonResponse
    {
        $this->authorizeTenant($request, $definition);
        $definition->delete();

        return response()->json(['message' => 'Workflow supprimé.']);
    }

    // ─── Toggle ────────────────────────────────────────────────────────────────

    /**
     * Activate or deactivate a workflow.
     *
     * PUT /api/v1/workflow-chain/definitions/{id}/toggle
     */
    public function toggle(Request $request, WorkflowChainDefinition $definition): JsonResponse
    {
        $this->authorizeTenant($request, $definition);

        $definition->update(['is_active' => ! $definition->is_active]);

        return response()->json([
            'id'        => $definition->id,
            'is_active' => $definition->is_active,
            'message'   => $definition->is_active ? 'Workflow activé.' : 'Workflow désactivé.',
        ]);
    }

    // ─── Test ──────────────────────────────────────────────────────────────────

    /**
     * Run a workflow with a sample context (dry-run).
     *
     * POST /api/v1/workflow-chain/definitions/{id}/test
     *
     * @bodyParam context array Sample trigger context to use.
     */
    public function test(Request $request, WorkflowChainDefinition $definition): JsonResponse
    {
        $this->authorizeTenant($request, $definition);

        $context  = $request->input('context', []);
        $actions  = is_array($definition->actions) ? $definition->actions : json_decode($definition->actions, true) ?? [];
        // Chantier 10: this used to hardcode HrPayrollActionHandler regardless
        // of the action's real module prefix, so dry-running any non-payroll/
        // hr/it chain (crm.*, achats.*, notify.*, ...) silently returned
        // "unknown_action" for every step. WorkflowEngineService::executeAction()
        // is the real, module-aware dispatcher every other execution path uses.
        $engine   = app(WorkflowEngineService::class);
        $results  = [];
        $allOk    = true;

        foreach ($actions as $action) {
            $key    = $action['action_key'] ?? '';
            $params = $action['params']     ?? [];

            $start  = microtime(true);
            try {
                $result = $engine->executeAction($key, $params, $context);
            } catch (\Throwable $e) {
                $result = ['status' => 'error', 'reason' => $e->getMessage()];
            }
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $results[] = [
                'action_key'  => $key,
                'status'      => $result['status'] ?? 'unknown',
                'output'      => $result,
                'duration_ms' => $durationMs,
            ];

            if (($result['status'] ?? '') === 'error') {
                $allOk = false;
            }
        }

        return response()->json([
            'definition_id' => $definition->id,
            'dry_run'       => true,
            'overall_status'=> $allOk ? 'success' : 'partial_failure',
            'context'       => $context,
            'results'       => $results,
        ]);
    }

    // ─── Executions ────────────────────────────────────────────────────────────

    /**
     * List execution history for a specific workflow definition.
     *
     * GET /api/v1/workflow-chain/definitions/{id}/executions
     *
     * @queryParam per_page integer. Example: 20
     * @queryParam status string Filter by status. Example: failed
     */
    public function executions(Request $request, WorkflowChainDefinition $definition): JsonResponse
    {
        $this->authorizeTenant($request, $definition);

        $perPage = min((int) ($request->per_page ?? 20), 100);

        $executions = WorkflowChainExecution::where('workflow_definition_id', $definition->id)
            ->where('tenant_id', $this->tenantId($request))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->with('steps')
            ->latest('started_at')
            ->paginate($perPage);

        return response()->json($executions);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Chantier 8.6: previously fell back to $user->id when tenant_id was
     * absent, scoping workflow chain definitions per-individual-user
     * instead of per-company — no cross-tenant leak (authorizeTenant()
     * below always compared against this same value), but colleagues at
     * the same company couldn't see each other's automations. Aligned to
     * company_id for consistency with every other tenant-boundary fix this
     * session.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }

    private function authorizeTenant(Request $request, WorkflowChainDefinition $definition): void
    {
        abort_unless($definition->tenant_id === $this->tenantId($request), 403, 'Accès non autorisé.');
    }
}
