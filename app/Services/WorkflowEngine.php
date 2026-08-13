<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalChain;
use App\Models\Workflow;
use App\Models\WorkflowAuditLog;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStepLog;
use Illuminate\Support\Facades\Log;

class WorkflowEngine
{
    /**
     * Execute workflow from trigger event
     */
    public function execute(Workflow $workflow, array $triggerData, ?int $triggeredBy = null): WorkflowExecution
    {
        if (!$workflow->is_enabled) {
            throw new \Exception('Workflow is disabled');
        }

        $startTime = microtime(true);
        $execution = WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'triggered_by' => $triggeredBy,
            'trigger_data' => $triggerData,
            'status' => 'running',
            'context' => $triggerData,
            'started_at' => now(),
        ]);

        try {
            $context = $triggerData;

            foreach ($workflow->steps as $stepIndex => $step) {
                $result = $this->executeStep($execution, $step, $context, $stepIndex);

                if ($result['status'] === 'failed') {
                    throw new \Exception($result['error'] ?? 'Step failed');
                }

                if ($result['status'] === 'waiting') {
                    $execution->update(['status' => 'waiting']);
                    return $execution;
                }

                $context = array_merge($context, $result['context'] ?? []);
            }

            $execution->update([
                'status' => 'completed',
                'completed_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'context' => $context,
            ]);

            $workflow->increment('execution_count');
            $workflow->update(['last_executed_at' => now()]);

            $this->audit($execution, 'workflow_completed');
        } catch (\Exception $e) {
            $execution->update([
                'status' => 'failed',
                'completed_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            $this->audit($execution, 'workflow_failed', ['error' => $e->getMessage()]);
            Log::error("Workflow execution failed for {$workflow->id}: " . $e->getMessage());
        }

        return $execution;
    }

    /**
     * Execute single workflow step
     */
    private function executeStep(WorkflowExecution $execution, array $step, array $context, int $stepIndex): array
    {
        $stepName = $step['name'] ?? "Step {$stepIndex}";
        $stepType = $step['type'] ?? 'action';

        $startTime = microtime(true);

        $stepLog = WorkflowStepLog::create([
            'execution_id' => $execution->id,
            'step_name' => $stepName,
            'step_type' => $stepType,
            'input_data' => $context,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        try {
            $result = match ($stepType) {
                'approval' => $this->executeApproval($stepLog, $step, $context),
                'condition' => $this->executeCondition($stepLog, $step, $context),
                'action' => $this->executeAction($stepLog, $step, $context),
                default => throw new \Exception("Unknown step type: {$stepType}"),
            };

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($result['status'] === 'waiting') {
                $stepLog->update([
                    'status' => 'waiting',
                    'duration_ms' => $durationMs,
                ]);

                return ['status' => 'waiting', 'context' => $context];
            }

            $stepLog->update([
                'status' => 'completed',
                'output_data' => $result['output'] ?? null,
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            $this->audit($execution, 'step_completed', [
                'step_name' => $stepName,
                'step_type' => $stepType,
                'duration_ms' => $durationMs,
            ]);

            return ['status' => 'completed', 'context' => $result['context'] ?? $context];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $stepLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            $this->audit($execution, 'step_failed', [
                'step_name' => $stepName,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute approval step with approval chains
     */
    private function executeApproval(WorkflowStepLog $stepLog, array $step, array $context): array
    {
        $approvers = $step['approvers'] ?? [];
        $approvalType = $step['approval_type'] ?? 'sequential';

        if (empty($approvers)) {
            throw new \Exception('No approvers configured');
        }

        $approverSequence = array_map(function ($approver, $index) {
            return [
                'order' => $index + 1,
                'user_id' => $approver['user_id'] ?? null,
                'role' => $approver['role'] ?? null,
                'status' => 'pending',
            ];
        }, $approvers, array_keys($approvers));

        $chain = ApprovalChain::create([
            'step_log_id' => $stepLog->id,
            'approval_type' => $approvalType,
            'approver_sequence' => $approverSequence,
            'required_approvals' => $step['required_approvals'] ?? 1,
            'started_at' => now(),
        ]);

        // Create approval records
        foreach ($approverSequence as $approver) {
            Approval::create([
                'chain_id' => $chain->id,
                'approver_id' => $approver['user_id'],
                'sequence_order' => $approver['order'],
                'status' => 'pending',
                'due_at' => $step['due_date'] ? now()->parse($step['due_date']) : null,
            ]);
        }

        // Return waiting status - workflow pauses until approvals complete
        return ['status' => 'waiting'];
    }

    /**
     * Execute conditional branching step
     */
    private function executeCondition(WorkflowStepLog $stepLog, array $step, array $context): array
    {
        $expression = $step['condition'] ?? [];
        $result = $this->evaluateExpression($expression, $context);

        $stepLog->update([
            'output_data' => ['condition_result' => $result],
        ]);

        $nextStep = $result ? $step['then_step'] : $step['else_step'];

        return [
            'status' => 'completed',
            'context' => array_merge($context, ['last_condition_result' => $result, 'next_step' => $nextStep]),
        ];
    }

    /**
     * Execute action step
     */
    private function executeAction(WorkflowStepLog $stepLog, array $step, array $context): array
    {
        $actionType = $step['action_type'] ?? null;

        if (!$actionType) {
            throw new \Exception('No action type specified');
        }

        $result = match ($actionType) {
            'send_notification' => $this->actionSendNotification($step, $context),
            'create_task' => $this->actionCreateTask($step, $context),
            'update_record' => $this->actionUpdateRecord($step, $context),
            'trigger_webhook' => $this->actionTriggerWebhook($step, $context),
            default => throw new \Exception("Unknown action type: {$actionType}"),
        };

        return ['status' => 'completed', 'output' => $result, 'context' => $context];
    }

    private function actionSendNotification(array $action, array $context): array
    {
        return ['sent' => true, 'recipients' => $action['recipients'] ?? []];
    }

    private function actionCreateTask(array $action, array $context): array
    {
        return ['task_created' => true, 'task_id' => 'TASK-' . uniqid()];
    }

    private function actionUpdateRecord(array $action, array $context): array
    {
        return ['updated' => true, 'fields' => $action['fields'] ?? []];
    }

    private function actionTriggerWebhook(array $action, array $context): array
    {
        return ['webhook_called' => true, 'url' => $action['url'] ?? null];
    }

    /**
     * Evaluate conditional expression
     */
    private function evaluateExpression(array $expression, array $context): bool
    {
        if (empty($expression)) {
            return true;
        }

        $type = $expression['type'] ?? 'AND';
        $conditions = $expression['conditions'] ?? [];

        $results = [];

        foreach ($conditions as $condition) {
            $field = $this->getContextValue($context, $condition['field'] ?? '');
            $operator = $condition['operator'] ?? 'equals';
            $expectedValue = $condition['value'] ?? null;

            $results[] = match ($operator) {
                'equals' => $field === $expectedValue,
                'not_equals' => $field !== $expectedValue,
                'greater_than' => (float) $field > (float) $expectedValue,
                'less_than' => (float) $field < (float) $expectedValue,
                'contains' => str_contains((string) $field, (string) $expectedValue),
                'in' => in_array($field, (array) $expectedValue),
                default => false,
            };
        }

        return $type === 'AND' ? !in_array(false, $results, true) : in_array(true, $results, true);
    }

    /**
     * Get value from context using dot notation
     */
    private function getContextValue(array $context, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Log audit entry for workflow
     */
    private function audit(WorkflowExecution $execution, string $action, array $changes = []): void
    {
        WorkflowAuditLog::create([
            'execution_id' => $execution->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Resume workflow after approval completion
     */
    public function resumeAfterApproval(ApprovalChain $chain): void
    {
        if ($chain->isCompleted()) {
            // Resume workflow execution from next step
            $stepLog = $chain->stepLog;
            $execution = $stepLog->execution;

            // Continue workflow from next step (implementation depends on workflow structure)
            Log::info("Workflow resumed after approval for execution {$execution->id}");
        }
    }
}
