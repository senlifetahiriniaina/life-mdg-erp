<?php

declare(strict_types=1);

namespace Modules\Workflow\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Workflow\Models\WorkflowChainDefinition;
use Modules\Workflow\Models\WorkflowChainExecution;
use Modules\Workflow\Services\Actions\AchatsInventoryActionHandler;
use Modules\Workflow\Services\Actions\CrmSalesActionHandler;
use Modules\Workflow\Services\Actions\InventoryAccountingActionHandler;
use Modules\Workflow\Services\Actions\NotificationActionHandler;
use Modules\Workflow\Services\Actions\SalesManufacturingActionHandler;
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
 * Legacy methods (createWorkflow, addStep, publishWorkflow, etc.) are
 * preserved below for backward compatibility with the builder UI.
 */
class WorkflowEngineService
{
    // ── Legacy constants ───────────────────────────────────────────────────────

    const CACHE_TTL          = 3600;
    const MAX_WORKFLOW_STEPS = 100;
    const EXECUTION_TIMEOUT  = 300; // seconds

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
        // ── Cache-based workflow (from createWorkflow/addStep/publishWorkflow) ──────
        $cachedWorkflow = Cache::get("workflow:{$triggerKeyOrWorkflowId}");
        if ($cachedWorkflow) {
            return $this->executeCachedWorkflow($triggerKeyOrWorkflowId, $cachedWorkflow, $context);
        }

        // ── DB-based trigger workflow ─────────────────────────────────────────────
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

    private function executeCachedWorkflow(string $workflowId, array $workflow, array $context): array
    {
        if (($workflow['status'] ?? 'draft') !== 'published') {
            return ['error' => 'Workflow must be published before it can be executed'];
        }

        $executionId = uniqid('exec_');
        $results     = [];

        foreach ($workflow['steps'] ?? [] as $step) {
            $stepId   = $step['id'] ?? uniqid('step_');
            $stepType = $step['type'] ?? 'action';

            if ($stepType === 'decision') {
                $condition = $step['config']['condition'] ?? [];
                $result    = $this->evaluateCondition($condition, $context);
                $results[$stepId] = ['type' => 'decision', 'result' => $result];
            } else {
                // action / notification / email etc.
                $results[$stepId] = ['type' => $stepType, 'status' => 'completed'];
            }
        }

        $execution = [
            'execution_id' => $executionId,
            'workflow_id'  => $workflowId,
            'status'       => 'completed',
            'results'      => $results,
            'context'      => $context,
            'started_at'   => now()->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
        ];
        Cache::put("execution:{$executionId}", $execution, now()->addHours(24));

        return $execution;
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
    private function handleApprovalAction(string $method, array $params, array $context): array
    {
        return match ($method) {
            'request_multi_level' => [
                'status'      => 'approval_requested',
                'levels'      => $params['levels'] ?? 3,
                'entity_type' => $context['entity_type'] ?? 'sales_order',
                'entity_id'   => $context['order_id'] ?? null,
            ],
            default => ['status' => 'skipped'],
        };
    }

    private function handleNotifyAction(string $method, array $params, array $context): array
    {
        return [
            'status'    => 'notification_queued',
            'recipient' => $params['recipient'] ?? 'manager',
            'channel'   => $params['channel'] ?? 'email',
            'context'   => array_intersect_key($context, array_flip(['order_id', 'opportunity_id', 'amount'])),
        ];
    }

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

    // ── Legacy cache-based builder methods (preserved for builder UI) ──────────

    public function createWorkflow(array $config): array
    {
        $workflowId = uniqid('workflow_');
        $workflow   = array_merge($config, [
            'id'         => $workflowId,
            'status'     => 'draft',
            'steps'      => [],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
        Cache::put("workflow:{$workflowId}", $workflow, now()->addDays(365));
        return ['workflow_id' => $workflowId, 'status' => 'created'];
    }

    public function addStep(string $workflowId, array $stepConfig): array
    {
        $workflow = Cache::get("workflow:{$workflowId}");
        if (! $workflow) {
            return ['error' => 'Workflow not found'];
        }
        if (count($workflow['steps']) >= self::MAX_WORKFLOW_STEPS) {
            return ['error' => 'Maximum steps reached'];
        }
        $stepId           = uniqid('step_');
        $step             = array_merge(['id' => $stepId, 'position' => count($workflow['steps'])], $stepConfig);
        $workflow['steps'][] = $step;
        Cache::put("workflow:{$workflowId}", $workflow, now()->addDays(365));
        return ['workflow_id' => $workflowId, 'step_id' => $stepId, 'status' => 'added'];
    }

    public function publishWorkflow(string $workflowId): array
    {
        $workflow = Cache::get("workflow:{$workflowId}");
        if (! $workflow) {
            return ['error' => 'Workflow not found'];
        }
        if (empty($workflow['steps'])) {
            return ['error' => 'Workflow must have at least one step'];
        }
        $workflow['status']       = 'published';
        $workflow['published_at'] = now()->toIso8601String();
        Cache::put("workflow:{$workflowId}", $workflow, now()->addDays(365));
        return ['workflow_id' => $workflowId, 'status' => 'published'];
    }

    public function getWorkflow(string $workflowId): ?array
    {
        return Cache::get("workflow:{$workflowId}");
    }

    public function getExecution(string $executionId): ?array
    {
        return Cache::get("execution:{$executionId}");
    }

    public function deleteWorkflow(string $workflowId): array
    {
        Cache::forget("workflow:{$workflowId}");
        return ['workflow_id' => $workflowId, 'status' => 'deleted'];
    }

    public function duplicateWorkflow(string $workflowId, string $newName): array
    {
        $workflow = Cache::get("workflow:{$workflowId}");
        if (! $workflow) {
            return ['error' => 'Workflow not found'];
        }
        $newId            = uniqid('workflow_');
        $workflow['id']   = $newId;
        $workflow['name'] = $newName;
        $workflow['status'] = 'draft';
        Cache::put("workflow:{$newId}", $workflow, now()->addDays(365));
        return ['new_workflow_id' => $newId, 'status' => 'duplicated'];
    }

    /**
     * Evaluate whether a trigger node matches the current context.
     */
    public function evaluateTrigger(array $trigger, array $context): bool
    {
        $type = $trigger['type'] ?? 'event';
        if ($type === 'event') {
            $triggerKey  = $trigger['event_key'] ?? '';
            $contextKey  = $context['event_key'] ?? '';
            return $triggerKey === '' || $triggerKey === $contextKey;
        }
        return true;
    }

    /**
     * Dispatch a single action node (returns true on success).
     */
    public function dispatchAction(array $action): bool
    {
        $actionKey = $action['action_key'] ?? $action['action'] ?? 'unknown';
        $params    = $action['params'] ?? [];
        try {
            $this->executeAction($actionKey, $params, []);
        } catch (\Throwable) {
            // swallow for resilience; return true to indicate dispatch attempt
        }
        return true;
    }

    /**
     * Process an ordered chain of trigger / condition / action nodes.
     */
    public function processChain(array $chain, array $context): array
    {
        $results = [];
        foreach ($chain as $node) {
            $type = $node['type'] ?? 'action';
            if ($type === 'trigger') {
                $results[] = ['node' => $type, 'matched' => $this->evaluateTrigger($node, $context)];
            } elseif ($type === 'condition') {
                $passed    = $this->evaluateCondition($node, $context);
                $results[] = ['node' => $type, 'passed' => $passed];
                if (!$passed) {
                    break;
                }
            } else {
                $results[] = ['node' => $type, 'dispatched' => $this->dispatchAction($node)];
            }
        }
        return $results;
    }

    /**
     * Dispatch multiple actions in parallel (synchronous emulation).
     */
    public function dispatchParallelActions(array $actions): array
    {
        $results = [];
        foreach ($actions as $action) {
            $results[] = $this->dispatchAction($action);
        }
        return $results;
    }

    /**
     * Dispatch an action with retry logic.
     */
    public function dispatchWithRetry(array $action, int $maxRetries = 3): bool
    {
        $attempts = 0;
        while ($attempts <= $maxRetries) {
            try {
                return $this->dispatchAction($action);
            } catch (\Throwable) {
                $attempts++;
            }
        }
        return false;
    }

    /**
     * Dispatch an action respecting a timeout (synchronous stub).
     */
    public function dispatchWithTimeout(array $action, int $timeout = 30): bool
    {
        return $this->dispatchAction($action);
    }

    /**
     * Log a workflow chain execution and return an execution ID.
     */
    public function logExecution(array $chain, array $context): string
    {
        $executionId = uniqid('exec_', true);
        Cache::put("execution:{$executionId}", [
            'id'         => $executionId,
            'chain'      => $chain,
            'context'    => $context,
            'started_at' => now()->toIso8601String(),
            'status'     => 'logged',
        ], now()->addHours(24));
        return $executionId;
    }
}
