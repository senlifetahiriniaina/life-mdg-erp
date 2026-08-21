<?php

declare(strict_types=1);

namespace Modules\Workflow\Services;

use Illuminate\Support\Facades\Log;
use Modules\Workflow\Models\WorkflowChainDefinition;
use Modules\Workflow\Models\WorkflowChainExecution;
use Modules\Workflow\Services\Actions\AchatsInventoryActionHandler;
use Modules\Workflow\Services\Actions\AiActionHandler;
use Modules\Workflow\Services\Actions\CalendarActionHandler;
use Modules\Workflow\Services\Actions\CrmSalesActionHandler;
use Modules\Workflow\Services\Actions\DataTransformHandler;
use Modules\Workflow\Services\Actions\DelayActionHandler;
use Modules\Workflow\Services\Actions\DocumentsActionHandler;
use Modules\Workflow\Services\Actions\EcommerceActionHandler;
use Modules\Workflow\Services\Actions\HelpdeskActionHandler;
use Modules\Workflow\Services\Actions\HttpActionHandler;
use Modules\Workflow\Services\Actions\InventoryAccountingActionHandler;
use Modules\Workflow\Services\Actions\LogisticsActionHandler;
use Modules\Workflow\Services\Actions\NotificationActionHandler;
use Modules\Workflow\Services\Actions\ProjectsActionHandler;
use Modules\Workflow\Services\Actions\QualityActionHandler;
use Modules\Workflow\Services\Actions\SalesManufacturingActionHandler;
use Modules\Workflow\Services\Actions\StrategyActionHandler;
use Modules\Workflow\Services\Actions\Phase52ActionHandler;
use Modules\Workflow\Services\Actions\HrPayrollActionHandler;

/**
 * WorkflowEngineService — Phase 39 CRM→Sales→Manufacturing Chain
 *
 * Responsibilities:
 *  - Find all active WorkflowChainDefinitions matching a trigger key
 *  - Evaluate conditions against the incoming context
 *  - Execute actions in sequence, logging each step
 *  - Record an execution row (pending → running → completed|failed)
 *
 * Chantier 32.11: this class previously also carried a second, entirely
 * separate Cache-backed "legacy" workflow builder API (createWorkflow/
 * addStep/publishWorkflow/getWorkflow/getExecution/deleteWorkflow/
 * duplicateWorkflow/evaluateTrigger/dispatchAction/processChain/
 * dispatchParallelActions/dispatchWithRetry/dispatchWithTimeout/
 * logExecution, plus two dead private helpers handleApprovalAction()/
 * handleNotifyAction()) — confirmed via grep to have zero real callers
 * anywhere outside its own isolated tests, "preserved for backward
 * compatibility with the builder UI" that turned out itself to be 100%
 * mock (AIWorkflowBuilder/Index.vue, already documented since Chantier
 * 8.5-light as having zero fetch calls). Deleted alongside the equally-dead
 * ApprovalWorkflowService/WorkflowBuilderService/TaskManagementService — see
 * this chantier's CLAUDE.md entry for the full rationale. The one real,
 * live method this class exposes below (executeWorkflow/runDefinition/
 * executeAction/evaluateConditions/evaluateCondition) is untouched.
 */
class WorkflowEngineService
{
    // ── Phase-39 trigger-based engine ─────────────────────────────────────────

    /**
     * Execute all active workflow definitions that match the given trigger key.
     *
     * @param  string               $triggerKey  e.g. 'crm.opportunity.won'
     * @param  array<string,mixed>  $context     Event payload (opportunity_id, amount, …)
     * @return array<int,array>     One result array per matched definition
     */
    public function executeWorkflow(string $triggerKeyOrWorkflowId, array $context): array
    {
        // Chantier 32.11: this method previously checked
        // Cache::get("workflow:{$id}") first, executing a Cache-backed
        // "legacy" workflow shape (`executeCachedWorkflow()`) if one was
        // found — but the only 3 methods that could ever have populated
        // that cache key (createWorkflow()/addStep()/publishWorkflow())
        // were confirmed to have zero real callers anywhere in the app
        // (only their own isolated tests and the already-documented 100%
        // mock AIWorkflowBuilder/Index.vue page, which makes no fetch call
        // at all) — deleted alongside WorkflowBuilderService/
        // TaskManagementService/ApprovalWorkflowService as the same
        // fully-dead-with-zero-producer pattern (see this chantier's
        // CLAUDE.md entry). The real, live callers of this method
        // (ExecuteWorkflowJob, WorkflowChainController::manualTrigger())
        // only ever pass a real trigger_key, never a legacy cache-backed
        // workflow id, so the branch removed here was permanently
        // unreachable in production.
        $tenantId    = $context['tenant_id'] ?? 1;
        $definitions = WorkflowChainDefinition::where('trigger_key', $triggerKeyOrWorkflowId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $results = [];
        foreach ($definitions as $definition) {
            $results[] = $this->runDefinition($definition, $context);
        }
        return $results;
    }

    /**
     * Run a single WorkflowChainDefinition against the given context.
     */
    private function runDefinition(WorkflowChainDefinition $definition, array $context): array
    {
        // Create execution record
        $execution = WorkflowChainExecution::create([
            'workflow_definition_id' => $definition->id,
            'tenant_id'              => $definition->tenant_id,
            'trigger_key'            => $definition->trigger_key,
            'context_snapshot'       => $context,
            'status'                 => 'running',
            'started_at'             => now(),
        ]);

        $resultLog = [];
        $overallStatus = 'completed';
        $errorMessage  = null;

        try {
            // 1. Evaluate conditions
            if (! $this->evaluateConditions($definition->conditions ?? [], $context)) {
                $execution->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                    'result_log'   => [['step' => 'conditions', 'result' => 'skipped — conditions not met']],
                ]);

                $definition->increment('execution_count');
                $definition->update(['last_executed_at' => now()]);

                return ['execution_id' => $execution->id, 'status' => 'skipped', 'reason' => 'conditions not met'];
            }

            // 2. Execute each action in sequence
            foreach ($definition->actions as $index => $action) {
                $stepStart  = microtime(true);
                $actionKey  = $action['action_key'] ?? $action['action'] ?? '';
                $params     = $action['params'] ?? [];

                try {
                    $actionResult = $this->executeAction($actionKey, $params, $context);

                    $resultLog[] = [
                        'step'        => $index + 1,
                        'action'      => $actionKey,
                        'status'      => 'success',
                        'result'      => $actionResult,
                        'duration_ms' => round((microtime(true) - $stepStart) * 1000),
                    ];

                    // Merge action results into context for subsequent actions
                    if (is_array($actionResult)) {
                        $context = array_merge($context, $actionResult);
                    }
                } catch (\Throwable $e) {
                    $resultLog[] = [
                        'step'    => $index + 1,
                        'action'  => $actionKey,
                        'status'  => 'error',
                        'error'   => $e->getMessage(),
                    ];

                    // Non-critical actions don't fail the whole chain
                    $isCritical = (bool) ($action['critical'] ?? true);
                    if ($isCritical) {
                        $overallStatus = 'failed';
                        $errorMessage  = "Action [{$actionKey}] failed: " . $e->getMessage();
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $overallStatus = 'failed';
            $errorMessage  = $e->getMessage();
            Log::error('WorkflowEngineService: unexpected error', [
                'definition_id' => $definition->id,
                'error'         => $e->getMessage(),
            ]);
        }

        // 3. Persist execution result
        $execution->update([
            'status'        => $overallStatus,
            'completed_at'  => now(),
            'result_log'    => $resultLog,
            'error_message' => $errorMessage,
        ]);

        $definition->increment('execution_count');
        $definition->update(['last_executed_at' => now()]);

        return [
            'execution_id' => $execution->id,
            'status'       => $overallStatus,
            'steps'        => count($resultLog),
            'result_log'   => $resultLog,
        ];
    }

    /**
     * Evaluate an array of conditions (AND logic).
     *
     * @param  array<int,array>     $conditions
     * @param  array<string,mixed>  $context
     */
    public function evaluateConditions(array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition object.
     *
     * Supported operators: equals, not_equals, greater_than, less_than,
     *                      contains, in, between, always
     *
     * @param  array<string,mixed>  $condition
     * @param  array<string,mixed>  $context
     */
    public function evaluateCondition(array $condition, array $context): bool
    {
        $operator = $condition['operator'] ?? 'always';

        if ($operator === 'always') {
            return true;
        }

        $field = $condition['field'] ?? '';
        // Support dot-notation: "order.total_amount"
        $value = $this->resolveContextValue($field, $context);
        $target = $condition['value'] ?? null;

        return match ($operator) {
            'equals'        => $value == $target,
            'not_equals'    => $value != $target,
            'greater_than'  => is_numeric($value) && $value > $target,
            'less_than'     => is_numeric($value) && $value < $target,
            'contains'      => is_string($value) && str_contains($value, (string) $target),
            'in'            => is_array($target) && in_array($value, $target, true),
            'between'       => is_array($target) && count($target) === 2
                               && $value >= $target[0] && $value <= $target[1],
            default         => true,
        };
    }

    /**
     * Dispatch an action key to the appropriate handler.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function executeAction(string $actionKey, array $params, array $context): array
    {
        [$module, $method] = array_pad(explode('.', $actionKey, 2), 2, '');

        return match ($module) {
            // ── Phase-39 CRM→Sales→Manufacturing (WF-001 to WF-004) ────────────
            'crm'           => app(CrmSalesActionHandler::class)->dispatch($method, $params, $context),
            'sales'         => app(CrmSalesActionHandler::class)->dispatch($method, $params, $context),
            'manufacturing' => app(SalesManufacturingActionHandler::class)->dispatch($method, $params, $context),
            // ── Phase-39 Achats→Inventory→Accounting (WF-005 to WF-009) ────────
            'achats'        => app(AchatsInventoryActionHandler::class)->dispatch($method, $params, $context),
            'inventory'     => app(AchatsInventoryActionHandler::class)->dispatch($method, $params, $context),
            'accounting'    => app(InventoryAccountingActionHandler::class)->dispatch($method, $params, $context),
            // ── Notifications & approvals ────────────────────────────────────────
            'notify'        => app(NotificationActionHandler::class)->dispatch($method, $params, $context),
            'approval'      => app(NotificationActionHandler::class)->dispatch($method, $params, $context),
            // ── HR→Payroll chain (WF-010 to WF-015) ─────────────────────────────
            // Chantier 10: 'hr'/'it' had no case at all here (silently fell to
            // the `default` "unknown action module" branch — HrPayrollActionHandler
            // was only ever reachable via 2 controllers that hardcoded it
            // directly, bypassing this dispatcher, so a real trigger-based
            // hr.*/it.* action never actually ran). 'payroll' is split: the 4
            // action keys HrPayrollActionHandler actually implements
            // (adjust_for_leave/add_overtime/enroll_new_employee/
            // calculate_final_settlement) go there; everything else (e.g.
            // payroll.generate_run/payroll.approve_payslip) keeps going to
            // Phase52ActionHandler as before.
            'hr'            => app(HrPayrollActionHandler::class)->dispatch($actionKey, $params, $context),
            'it'            => app(HrPayrollActionHandler::class)->dispatch($actionKey, $params, $context),
            'payroll'       => in_array($method, ['adjust_for_leave', 'add_overtime', 'enroll_new_employee', 'calculate_final_settlement'], true)
                ? app(HrPayrollActionHandler::class)->dispatch($actionKey, $params, $context)
                : $this->dispatchPhase52($module, $method, $params, $context),
            // ── Phase-52 new modules ──────────────────────────────────────────────
            'assets'         => $this->dispatchPhase52($module, $method, $params, $context),
            'contracts'      => $this->dispatchPhase52($module, $method, $params, $context),
            'sms'            => $this->dispatchPhase52($module, $method, $params, $context),
            'customerservice'=> $this->dispatchPhase52($module, $method, $params, $context),
            'auditlog'       => $this->dispatchPhase52($module, $method, $params, $context),
            'notes'          => $this->dispatchPhase52($module, $method, $params, $context),
            'settings'       => $this->dispatchPhase52($module, $method, $params, $context),
            'smarttable'     => $this->dispatchPhase52($module, $method, $params, $context),
            'reporting'      => $this->dispatchPhase52($module, $method, $params, $context),
            'shared'         => $this->dispatchPhase52($module, $method, $params, $context),
            // Chantier 32.11: 'ai'/'calendar'/'transform'/'delay'/'documents'/
            // 'ecommerce'/'helpdesk'/'http'/'logistics'/'projects'/'quality'/
            // 'strategy' had no case at all here despite their 12 handlers
            // being real, fully-written, singleton-bound in
            // WorkflowServiceProvider, and passed into WorkflowActionRegistry
            // — but this method (executeAction(), called from
            // runDefinition() below, the real dispatch path for every live
            // WorkflowChainDefinition execution) never routed to any of
            // them, so any real workflow action using one of these 12
            // prefixes silently fell to the `default` "Unknown action
            // module" branch below, confirmed empirically via tinker before
            // this fix. Each handler's dispatch() match() recognizes the
            // FULL action key (e.g. 'strategy.flag_ratio_alert'), not just
            // the method suffix — matching the same $actionKey-based calling
            // convention already used above for 'hr'/'it'/'payroll'.
            'ai'             => app(AiActionHandler::class)->dispatch($actionKey, $params, $context),
            'calendar'       => app(CalendarActionHandler::class)->dispatch($actionKey, $params, $context),
            'transform'      => app(DataTransformHandler::class)->dispatch($actionKey, $params, $context),
            'delay'          => app(DelayActionHandler::class)->dispatch($actionKey, $params, $context),
            'documents'      => app(DocumentsActionHandler::class)->dispatch($actionKey, $params, $context),
            'ecommerce'      => app(EcommerceActionHandler::class)->dispatch($actionKey, $params, $context),
            'helpdesk'       => app(HelpdeskActionHandler::class)->dispatch($actionKey, $params, $context),
            'http'           => app(HttpActionHandler::class)->dispatch($actionKey, $params, $context),
            'logistics'      => app(LogisticsActionHandler::class)->dispatch($actionKey, $params, $context),
            'projects'       => app(ProjectsActionHandler::class)->dispatch($actionKey, $params, $context),
            'quality'        => app(QualityActionHandler::class)->dispatch($actionKey, $params, $context),
            'strategy'       => app(StrategyActionHandler::class)->dispatch($actionKey, $params, $context),
            default          => ['status' => 'skipped', 'reason' => "Unknown action module: {$module}"],
        };
    }

    // ── Built-in action handlers ───────────────────────────────────────────────


    private function dispatchPhase52(string $module, string $method, array $params, array $context): array
    {
        $handler = app(Phase52ActionHandler::class);
        $camelMethod = lcfirst(str_replace(' ', '', ucwords(str_replace(['.', '-', '_'], ' ', $method))));

        if (method_exists($handler, $camelMethod)) {
            return $handler->$camelMethod($params, $context);
        }

        Log::warning("[Workflow] Phase52ActionHandler: no method for {$module}.{$method}");
        return ['status' => 'skipped', 'reason' => "No handler for {$module}.{$method}"];
    }
    // Chantier 32.11: handleApprovalAction()/handleNotifyAction() (both
    // private) were dead code — never called from anywhere in this class,
    // including their own match() dispatcher above, which routes 'approval'/
    // 'notify' action keys directly to the real NotificationActionHandler
    // instead. Deleted rather than left as unreachable private methods.

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveContextValue(string $field, array $context): mixed
    {
        if (str_contains($field, '.')) {
            $parts  = explode('.', $field, 2);
            $nested = $context[$parts[0]] ?? [];
            return is_array($nested) ? ($nested[$parts[1]] ?? null) : null;
        }

        return $context[$field] ?? null;
    }

}
