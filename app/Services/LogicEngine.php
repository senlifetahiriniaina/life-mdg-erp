<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LogicRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LogicEngine
{
    /**
     * Evaluate rule conditions against data
     */
    public function evaluate(LogicRule $rule, array $data): array
    {
        $startTime = microtime(true);

        try {
            $conditionResult = $this->evaluateConditions($rule->conditions, $data);
            $actionsToExecute = $conditionResult ? $rule->actions : [];

            $result = [
                'conditions_met' => $conditionResult,
                'condition_details' => $this->getConditionDetails($rule->conditions, $data),
                'actions_to_execute' => $actionsToExecute,
                'preview' => $this->generatePreview($rule, $conditionResult, $actionsToExecute),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];

            return $result;
        } catch (\Exception $e) {
            Log::error("Logic engine evaluation failed for rule {$rule->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute rule (evaluate + apply actions)
     */
    public function execute(LogicRule $rule, array $triggerData): array
    {
        $startTime = microtime(true);
        $error = null;
        $actionsExecuted = [];

        try {
            if (!$rule->is_enabled) {
                return ['skipped' => true, 'reason' => 'Rule is disabled'];
            }

            $conditionsMet = $this->evaluateConditions($rule->conditions, $triggerData);

            if ($conditionsMet) {
                $actionsExecuted = $this->executeActions($rule->actions, $triggerData);
            }

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Record execution
            $rule->recordExecution($conditionsMet, $actionsExecuted, null, $durationMs);

            return [
                'success' => true,
                'conditions_met' => $conditionsMet,
                'actions_executed' => $actionsExecuted,
                'duration_ms' => $durationMs,
            ];
        } catch (\Exception $e) {
            $error = $e->getMessage();
            Log::error("Logic rule execution failed for rule {$rule->id}: {$error}");

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $rule->recordExecution(false, $actionsExecuted, $error, $durationMs);

            return [
                'success' => false,
                'error' => $error,
                'duration_ms' => $durationMs,
            ];
        }
    }

    /**
     * Recursively evaluate condition tree (AND/OR logic)
     */
    private function evaluateConditions(array $conditions, array $data): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $type = $conditions['type'] ?? 'AND';
        $rules = $conditions['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        $results = [];

        foreach ($rules as $rule) {
            if (isset($rule['type'])) {
                // Nested condition group
                $results[] = $this->evaluateConditions($rule, $data);
            } else {
                // Single condition
                $results[] = $this->evaluateSingleCondition($rule, $data);
            }
        }

        if ($type === 'AND') {
            return !in_array(false, $results, true);
        } elseif ($type === 'OR') {
            return in_array(true, $results, true);
        }

        return true;
    }

    /**
     * Evaluate single condition (field operator value)
     */
    private function evaluateSingleCondition(array $condition, array $data): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $actualValue = $this->getNestedValue($data, $field);

        return match ($operator) {
            'equals' => $actualValue === $expectedValue || (string) $actualValue === (string) $expectedValue,
            'not_equals' => $actualValue !== $expectedValue && (string) $actualValue !== (string) $expectedValue,
            'contains' => str_contains((string) $actualValue, (string) $expectedValue),
            'not_contains' => !str_contains((string) $actualValue, (string) $expectedValue),
            'starts_with' => str_starts_with((string) $actualValue, (string) $expectedValue),
            'ends_with' => str_ends_with((string) $actualValue, (string) $expectedValue),
            'greater_than' => (float) $actualValue > (float) $expectedValue,
            'greater_than_or_equal' => (float) $actualValue >= (float) $expectedValue,
            'less_than' => (float) $actualValue < (float) $expectedValue,
            'less_than_or_equal' => (float) $actualValue <= (float) $expectedValue,
            'is_empty' => empty($actualValue),
            'is_not_empty' => !empty($actualValue),
            'is_true' => (bool) $actualValue === true,
            'is_false' => (bool) $actualValue === false,
            'in_list' => in_array($actualValue, (array) $expectedValue),
            'not_in_list' => !in_array($actualValue, (array) $expectedValue),
            default => false,
        };
    }

    /**
     * Execute all actions
     */
    private function executeActions(array $actions, array $triggerData): array
    {
        $executed = [];

        foreach ($actions as $action) {
            try {
                $result = $this->executeAction($action, $triggerData);
                $executed[] = array_merge($action, ['status' => 'success', 'result' => $result]);
            } catch (\Exception $e) {
                $executed[] = array_merge($action, ['status' => 'failed', 'error' => $e->getMessage()]);
            }
        }

        return $executed;
    }

    /**
     * Execute single action
     */
    private function executeAction(array $action, array $triggerData): array
    {
        $type = $action['type'] ?? null;

        return match ($type) {
            'update_field' => $this->actionUpdateField($action, $triggerData),
            'update_status' => $this->actionUpdateStatus($action, $triggerData),
            'send_email' => $this->actionSendEmail($action, $triggerData),
            'create_task' => $this->actionCreateTask($action, $triggerData),
            'assign_to_user' => $this->actionAssignToUser($action, $triggerData),
            'add_tag' => $this->actionAddTag($action, $triggerData),
            'add_comment' => $this->actionAddComment($action, $triggerData),
            'trigger_webhook' => $this->actionTriggerWebhook($action, $triggerData),
            default => throw new \Exception("Unknown action type: {$type}"),
        };
    }

    private function actionUpdateField(array $action, array $triggerData): array
    {
        return [
            'type' => 'update_field',
            'field' => $action['field'] ?? null,
            'value' => $action['value'] ?? null,
            'timestamp' => now(),
        ];
    }

    private function actionUpdateStatus(array $action, array $triggerData): array
    {
        return [
            'type' => 'update_status',
            'status' => $action['status'] ?? null,
            'timestamp' => now(),
        ];
    }

    private function actionSendEmail(array $action, array $triggerData): array
    {
        return [
            'type' => 'send_email',
            'template' => $action['template'] ?? null,
            'recipients' => $action['recipients'] ?? [],
            'timestamp' => now(),
        ];
    }

    private function actionCreateTask(array $action, array $triggerData): array
    {
        return [
            'type' => 'create_task',
            'title' => $action['title'] ?? null,
            'project_id' => $action['project_id'] ?? null,
            'timestamp' => now(),
        ];
    }

    private function actionAssignToUser(array $action, array $triggerData): array
    {
        return [
            'type' => 'assign_to_user',
            'user_id' => $action['user_id'] ?? null,
            'timestamp' => now(),
        ];
    }

    private function actionAddTag(array $action, array $triggerData): array
    {
        return [
            'type' => 'add_tag',
            'tag' => $action['tag'] ?? null,
            'timestamp' => now(),
        ];
    }

    private function actionAddComment(array $action, array $triggerData): array
    {
        return [
            'type' => 'add_comment',
            'text' => $action['text'] ?? null,
            'timestamp' => now(),
        ];
    }

    private function actionTriggerWebhook(array $action, array $triggerData): array
    {
        return [
            'type' => 'trigger_webhook',
            'url' => $action['webhook_url'] ?? null,
            'timestamp' => now(),
        ];
    }

    /**
     * Get value from nested array using dot notation
     */
    private function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

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
     * Get detailed evaluation of conditions (for UI preview)
     */
    private function getConditionDetails(array $conditions, array $data): array
    {
        return $this->detailConditions($conditions, $data);
    }

    private function detailConditions(array $conditions, array $data): array
    {
        if (empty($conditions)) {
            return [];
        }

        $type = $conditions['type'] ?? 'AND';
        $rules = $conditions['rules'] ?? [];
        $details = ['type' => $type, 'rules' => []];

        foreach ($rules as $rule) {
            if (isset($rule['type'])) {
                $details['rules'][] = $this->detailConditions($rule, $data);
            } else {
                $field = $rule['field'] ?? null;
                $operator = $rule['operator'] ?? 'equals';
                $expectedValue = $rule['value'] ?? null;
                $actualValue = $this->getNestedValue($data, $field);
                $isMet = $this->evaluateSingleCondition($rule, $data);

                $details['rules'][] = [
                    'field' => $field,
                    'operator' => $operator,
                    'expected_value' => $expectedValue,
                    'actual_value' => $actualValue,
                    'is_met' => $isMet,
                ];
            }
        }

        return $details;
    }

    /**
     * Generate human-readable preview of rule execution
     */
    private function generatePreview(LogicRule $rule, bool $conditionsMet, array $actions): string
    {
        $preview = "IF {$this->conditionsToString($rule->conditions)} ";
        $preview .= $conditionsMet ? "(✓ TRUE) " : "(✗ FALSE) ";
        $preview .= "THEN:\n";

        foreach ($actions as $action) {
            $preview .= "  - " . $this->actionToString($action) . "\n";
        }

        return $preview;
    }

    private function conditionsToString(array $conditions): string
    {
        if (empty($conditions)) {
            return "true";
        }

        $type = $conditions['type'] ?? 'AND';
        $rules = $conditions['rules'] ?? [];

        $parts = [];
        foreach ($rules as $rule) {
            if (isset($rule['type'])) {
                $parts[] = "(" . $this->conditionsToString($rule) . ")";
            } else {
                $field = $rule['field'] ?? 'field';
                $operator = $rule['operator'] ?? '=';
                $value = $rule['value'] ?? 'value';
                $parts[] = "$field $operator $value";
            }
        }

        return implode(" $type ", $parts);
    }

    private function actionToString(array $action): string
    {
        $type = $action['type'] ?? 'unknown';

        return match ($type) {
            'update_field' => "Update {$action['field']} = {$action['value']}",
            'update_status' => "Update status to {$action['status']}",
            'send_email' => "Send email using {$action['template']}",
            'create_task' => "Create task: {$action['title']}",
            'assign_to_user' => "Assign to user {$action['user_id']}",
            'add_tag' => "Add tag: {$action['tag']}",
            'add_comment' => "Add comment",
            'trigger_webhook' => "Call webhook",
            default => $type,
        };
    }
}
