<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\Projects\Models\AutomationRule;
use Modules\Projects\Models\Task;

class AutomationService
{
    /**
     * Evaluate all active automation rules matching the given trigger.
     *
     * @param  array<string,mixed>  $payload
     */
    public function evaluate(string $trigger, array $payload): void
    {
        $projectId = $payload['project_id'] ?? null;

        $rules = AutomationRule::where('trigger', $trigger)
            ->where('active', true)
            ->where(function ($q) use ($projectId): void {
                $q->whereNull('project_id');
                if ($projectId !== null) {
                    $q->orWhere('project_id', $projectId);
                }
            })
            ->get();

        foreach ($rules as $rule) {
            try {
                if ($this->matchesConditions($rule->conditions, $payload)) {
                    $this->executeActions($rule->actions, $payload);
                }
            } catch (\Throwable $e) {
                Log::warning('AutomationService: rule {id} failed — {msg}', [
                    'id' => $rule->id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check whether all conditions are satisfied by the payload.
     *
     * @param  array<string,mixed>  $conditions
     * @param  array<string,mixed>  $payload
     */
    private function matchesConditions(array $conditions, array $payload): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if ($field === null) {
                continue;
            }

            $actual = $payload[$field] ?? null;

            $match = match ($operator) {
                '!=' => $actual !== $value,
                'contains' => is_string($actual) && str_contains($actual, (string) $value),
                'in' => is_array($value) && in_array($actual, $value, true),
                default => $actual === $value,  // '=' or '=='
            };

            if (! $match) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute all actions defined by the rule.
     *
     * @param  array<string,mixed>  $actions
     * @param  array<string,mixed>  $payload
     */
    private function executeActions(array $actions, array $payload): void
    {
        $taskId = $payload['task_id'] ?? null;

        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $value = $action['value'] ?? null;

            switch ($type) {
                case 'assign_user':
                    if ($taskId !== null && $value !== null) {
                        Task::where('id', $taskId)->update(['assignee_id' => $value]);
                    }
                    break;

                case 'change_status':
                    if ($taskId !== null && $value !== null) {
                        Task::where('id', $taskId)->update(['status' => $value]);
                    }
                    break;

                case 'add_label':
                    if ($taskId !== null && $value !== null) {
                        $task = Task::find($taskId);
                        if ($task) {
                            /** @var array<int,string> $tags */
                            $tags = $task->tags ?? [];
                            $tags[] = (string) $value;
                            $task->update(['tags' => array_unique($tags)]);
                        }
                    }
                    break;

                case 'send_notification':
                    // Best-effort notification via Laravel's Log channel for now.
                    Log::info('AutomationService notification', [
                        'message' => $value,
                        'payload' => $payload,
                    ]);
                    break;

                default:
                    Log::debug("AutomationService: unknown action type '{$type}'");
            }
        }
    }
}
