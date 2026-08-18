<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Workflow\Models\WorkflowChainExecution;
use Modules\Workflow\Services\WorkflowEngineService;

/**
 * @group Workflow Chain Executions
 *
 * View execution history, per-step details, and retry failed executions.
 */
class WorkflowExecutionController extends Controller
{
    // ─── Index ─────────────────────────────────────────────────────────────────

    /**
     * List executions (paginated), with optional filters.
     *
     * @queryParam status string Filter: pending|running|completed|failed. Example: failed
     * @queryParam definition_id int Filter by workflow definition. Example: 5
     * @queryParam date_from string ISO date. Example: 2026-05-01
     * @queryParam date_to string ISO date. Example: 2026-05-31
     * @queryParam per_page int Max 100. Example: 25
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->per_page ?? 25), 100);

        $query = WorkflowChainExecution::where('tenant_id', $tenantId)
            ->with(['definition:id,name,trigger_key,trigger_module'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('definition_id'), fn ($q) => $q->where('workflow_definition_id', (int) $request->definition_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('started_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('started_at', '<=', $request->date_to))
            ->latest('started_at');

        $executions = $query->paginate($perPage);

        // Append stats summary
        $statsQuery = WorkflowChainExecution::where('tenant_id', $tenantId);
        $stats = [
            'total_today'     => (clone $statsQuery)->whereDate('created_at', today())->count(),
            'success_today'   => (clone $statsQuery)->whereDate('created_at', today())->where('status', 'completed')->count(),
            'failed_today'    => (clone $statsQuery)->whereDate('created_at', today())->where('status', 'failed')->count(),
            'avg_duration_ms' => (int) round(
                (clone $statsQuery)->whereNotNull('completed_at')->whereNotNull('started_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at) * 1000) as avg_ms')
                    ->value('avg_ms') ?? 0
            ),
        ];

        return response()->json([
            'data'       => $executions->items(),
            'pagination' => [
                'current_page' => $executions->currentPage(),
                'last_page'    => $executions->lastPage(),
                'per_page'     => $executions->perPage(),
                'total'        => $executions->total(),
            ],
            'stats' => $stats,
        ]);
    }

    // ─── Show ──────────────────────────────────────────────────────────────────

    /**
     * Show a single execution including its step details.
     */
    public function show(Request $request, WorkflowChainExecution $execution): JsonResponse
    {
        $this->authorizeTenant($request, $execution);

        $execution->load(['definition', 'steps']);

        return response()->json($execution->append(['duration_ms']));
    }

    // ─── Retry ─────────────────────────────────────────────────────────────────

    /**
     * Re-run a failed execution with the same context snapshot.
     *
     * POST /api/v1/workflow-chain/executions/{id}/retry
     */
    public function retry(Request $request, WorkflowChainExecution $execution): JsonResponse
    {
        $this->authorizeTenant($request, $execution);

        if ($execution->status !== 'failed') {
            return response()->json(['message' => 'Seules les exécutions échouées peuvent être relancées.'], 422);
        }

        $definition = $execution->definition;

        if (! $definition || ! $definition->is_active) {
            return response()->json(['message' => 'La définition de workflow est inactive ou introuvable.'], 422);
        }

        // Create a new execution with the same context
        $newExecution = WorkflowChainExecution::create([
            'workflow_definition_id' => $definition->id,
            'tenant_id'              => $execution->tenant_id,
            'trigger_key'            => $execution->trigger_key,
            'context_snapshot'       => $execution->context_snapshot,
            'status'                 => 'running',
            'started_at'             => now(),
        ]);

        $actions  = is_array($definition->actions) ? $definition->actions : json_decode($definition->actions, true) ?? [];
        // Chantier 10: this used to hardcode HrPayrollActionHandler regardless
        // of the action's real module prefix (crm.*, achats.*, notify.*, ...),
        // so retrying any non-payroll/hr/it chain silently returned
        // "unknown_action" for every step. WorkflowEngineService::executeAction()
        // is the real, module-aware dispatcher every other execution path uses.
        $engine    = app(WorkflowEngineService::class);
        $resultLog = [];
        $allOk    = true;

        foreach ($actions as $index => $action) {
            $key     = $action['action_key'] ?? '';
            $params  = $action['params']     ?? [];
            $context = $execution->context_snapshot ?? [];

            $start = microtime(true);
            try {
                $result = $engine->executeAction($key, $params, $context);
                $status = ($result['status'] ?? 'unknown') === 'error' ? 'failed' : 'completed';
            } catch (\Throwable $e) {
                $result = ['status' => 'error', 'reason' => $e->getMessage()];
                $status = 'failed';
            }
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $stepData = [
                'execution_id'  => $newExecution->id,
                'tenant_id'     => $newExecution->tenant_id,
                'step_index'    => $index,
                'action_key'    => $key,
                'input_context' => $context,
                'output'        => $result,
                'status'        => $status,
                'duration_ms'   => $durationMs,
                'executed_at'   => now(),
            ];

            DB::table('workflow_execution_steps')->insert(array_merge($stepData, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $resultLog[] = $stepData;

            if ($status === 'failed') {
                $allOk = false;
            }
        }

        $finalStatus = $allOk ? 'completed' : 'failed';
        $newExecution->update([
            'status'       => $finalStatus,
            'completed_at' => now(),
            'result_log'   => $resultLog,
        ]);

        // Update definition stats
        $definition->increment('execution_count');
        $definition->update(['last_executed_at' => now()]);

        return response()->json([
            'original_execution_id' => $execution->id,
            'new_execution'         => $newExecution->fresh(['steps']),
            'status'                => $finalStatus,
        ], 201);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->tenant_id ?? $request->user()?->id ?? 1);
    }

    private function authorizeTenant(Request $request, WorkflowChainExecution $execution): void
    {
        abort_unless($execution->tenant_id === $this->tenantId($request), 403, 'Accès non autorisé.');
    }
}
