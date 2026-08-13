<?php

namespace Modules\Workflow\Services;

use Illuminate\Support\Facades\Cache;

class TaskManagementService
{
    const CACHE_TTL = 86400;
    const PRIORITY_LEVELS = ['low', 'medium', 'high', 'urgent'];
    const TASK_STATUSES = ['open', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    /**
     * Create task
     */
    public function createTask(array $config): array
    {
        $taskId = uniqid('task_');

        $task = [
            'id' => $taskId,
            'title' => $config['title'],
            'description' => $config['description'] ?? '',
            'status' => 'open',
            'priority' => $config['priority'] ?? 'medium',
            'assigned_to' => $config['assigned_to'] ?? [],
            'created_by' => $config['created_by'],
            'workflow_id' => $config['workflow_id'] ?? null,
            'execution_id' => $config['execution_id'] ?? null,
            'due_date' => $config['due_date'] ?? null,
            'parent_task_id' => $config['parent_task_id'] ?? null,
            'subtasks' => [],
            'attachments' => [],
            'comments' => [],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put("task:{$taskId}", $task, now()->addDays(365));

        // Track task IDs for enumeration (works with any cache driver)
        $taskIds = Cache::get('task_ids', []);
        $taskIds[] = $taskId;
        Cache::put('task_ids', $taskIds, now()->addDays(365));

        return [
            'task_id' => $taskId,
            'status' => 'created',
        ];
    }

    /**
     * Assign task to users
     */
    public function assignTask(string $taskId, array $userIds, string $role = 'assignee'): array
    {
        $task = Cache::get("task:{$taskId}");

        if (!$task) {
            return ['error' => 'Task not found'];
        }

        foreach ($userIds as $userId) {
            if (!in_array($userId, $task['assigned_to'])) {
                $task['assigned_to'][] = [
                    'user_id' => $userId,
                    'role' => $role, // assignee, reviewer, observer
                    'assigned_at' => now()->toIso8601String(),
                ];
            }
        }

        $task['updated_at'] = now()->toIso8601String();
        Cache::put("task:{$taskId}", $task, now()->addDays(365));

        return [
            'task_id' => $taskId,
            'assigned_count' => count($userIds),
            'status' => 'assigned',
        ];
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(string $taskId, string $status, ?string $notes = null): array
    {
        $task = Cache::get("task:{$taskId}");

        if (!$task) {
            return ['error' => 'Task not found'];
        }

        if (!in_array($status, self::TASK_STATUSES)) {
            return ['error' => 'Invalid status'];
        }

        $oldStatus = $task['status'];
        $task['status'] = $status;
        $task['updated_at'] = now()->toIso8601String();

        if ($status === 'completed') {
            $task['completed_at'] = now()->toIso8601String();
        }

        // Add status change to comments
        if ($notes) {
            $task['comments'][] = [
                'id' => uniqid('comment_'),
                'type' => 'status_change',
                'from' => $oldStatus,
                'to' => $status,
                'note' => $notes,
                'timestamp' => now()->toIso8601String(),
            ];
        }

        Cache::put("task:{$taskId}", $task, now()->addDays(365));

        return [
            'task_id' => $taskId,
            'old_status' => $oldStatus,
            'new_status' => $status,
        ];
    }

    /**
     * Add comment to task
     */
    public function addComment(string $taskId, int $userId, string $comment, array $mentions = []): array
    {
        $task = Cache::get("task:{$taskId}");

        if (!$task) {
            return ['error' => 'Task not found'];
        }

        $task['comments'][] = [
            'id' => uniqid('comment_'),
            'user_id' => $userId,
            'text' => $comment,
            'mentions' => $mentions,
            'timestamp' => now()->toIso8601String(),
        ];

        $task['updated_at'] = now()->toIso8601String();
        Cache::put("task:{$taskId}", $task, now()->addDays(365));

        return [
            'task_id' => $taskId,
            'comment_id' => $task['comments'][count($task['comments']) - 1]['id'],
            'status' => 'added',
        ];
    }

    /**
     * Add attachment to task
     */
    public function addAttachment(string $taskId, string $fileName, string $filePath): array
    {
        $task = Cache::get("task:{$taskId}");

        if (!$task) {
            return ['error' => 'Task not found'];
        }

        $task['attachments'][] = [
            'id' => uniqid('attachment_'),
            'filename' => $fileName,
            'path' => $filePath,
            'size' => filesize($filePath) ?? 0,
            'uploaded_at' => now()->toIso8601String(),
        ];

        $task['updated_at'] = now()->toIso8601String();
        Cache::put("task:{$taskId}", $task, now()->addDays(365));

        return [
            'task_id' => $taskId,
            'attachment_id' => $task['attachments'][count($task['attachments']) - 1]['id'],
            'status' => 'uploaded',
        ];
    }

    /**
     * Create subtask
     */
    public function createSubtask(string $parentTaskId, array $config): array
    {
        $parentTask = Cache::get("task:{$parentTaskId}");

        if (!$parentTask) {
            return ['error' => 'Parent task not found'];
        }

        $subtaskId = uniqid('subtask_');

        $subtask = [
            'id' => $subtaskId,
            'title' => $config['title'],
            'assigned_to' => $config['assigned_to'] ?? null,
            'status' => 'open',
            'due_date' => $config['due_date'] ?? null,
            'created_at' => now()->toIso8601String(),
        ];

        $parentTask['subtasks'][] = $subtask;
        $parentTask['updated_at'] = now()->toIso8601String();

        Cache::put("task:{$parentTaskId}", $parentTask, now()->addDays(365));

        return [
            'parent_task_id' => $parentTaskId,
            'subtask_id' => $subtaskId,
            'status' => 'created',
        ];
    }

    /**
     * Update subtask status
     */
    public function updateSubtaskStatus(string $parentTaskId, string $subtaskId, string $status): array
    {
        $parentTask = Cache::get("task:{$parentTaskId}");

        if (!$parentTask) {
            return ['error' => 'Parent task not found'];
        }

        $subtaskIndex = $this->findSubtaskIndex($parentTask['subtasks'], $subtaskId);

        if ($subtaskIndex === -1) {
            return ['error' => 'Subtask not found'];
        }

        $parentTask['subtasks'][$subtaskIndex]['status'] = $status;
        $parentTask['updated_at'] = now()->toIso8601String();

        Cache::put("task:{$parentTaskId}", $parentTask, now()->addDays(365));

        return [
            'parent_task_id' => $parentTaskId,
            'subtask_id' => $subtaskId,
            'status' => 'updated',
        ];
    }

    /**
     * Get task
     */
    public function getTask(string $taskId): ?array
    {
        return Cache::get("task:{$taskId}");
    }

    /**
     * Get user tasks
     */
    private function getAllTaskIds(): array
    {
        try {
            $redisKeys = Cache::getRedis()->keys('task:*');
            return array_map(fn($k) => preg_replace('/^.*task:/', 'task:', $k), $redisKeys);
        } catch (\Throwable) {
            return array_map(fn($id) => "task:{$id}", Cache::get('task_ids', []));
        }
    }

    public function getUserTasks(int $userId, string $status = null): array
    {
        $keys = $this->getAllTaskIds();
        $userTasks = [];

        foreach ($keys as $key) {
            $task = Cache::get($key);

            if (!$task) {
                continue;
            }

            $isAssigned = collect($task['assigned_to'])->contains(fn($a) => $a['user_id'] === $userId);

            if ($isAssigned && ($status === null || $task['status'] === $status)) {
                $userTasks[] = $task;
            }
        }

        return $userTasks;
    }

    /**
     * Get overdue tasks
     */
    public function getOverdueTasks(): array
    {
        $keys = $this->getAllTaskIds();
        $overdueTasks = [];

        foreach ($keys as $key) {
            $task = Cache::get($key);

            if (!$task || !$task['due_date'] || $task['status'] === 'completed') {
                continue;
            }

            if (strtotime($task['due_date']) < time()) {
                $overdueTasks[] = $task;
            }
        }

        return $overdueTasks;
    }

    /**
     * Delete task
     */
    public function deleteTask(string $taskId): array
    {
        Cache::forget("task:{$taskId}");

        return [
            'task_id' => $taskId,
            'status' => 'deleted',
        ];
    }

    /**
     * Helper: Find subtask index
     */
    private function findSubtaskIndex(array $subtasks, string $subtaskId): int
    {
        foreach ($subtasks as $index => $subtask) {
            if ($subtask['id'] === $subtaskId) {
                return $index;
            }
        }

        return -1;
    }
}
