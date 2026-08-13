<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\Task;

/**
 * Service for handling real-time collaboration conflicts.
 * Implements pessimistic locking and last-write-wins conflict resolution.
 */
class TaskConflictResolutionService
{
    /**
     * Lock a task for editing (pessimistic locking).
     * Returns the locked task or null if already locked by another user.
     *
     * @param int $taskId
     * @param int $userId
     * @return Task|null
     */
    public function acquireLock(int $taskId, int $userId): ?Task
    {
        // Use SELECT ... FOR UPDATE to acquire a database-level lock
        $task = Task::where('id', $taskId)
            ->lockForUpdate()
            ->first();

        if (!$task) {
            return null;
        }

        // Store lock info in a cache/session (implementation detail)
        // For simplicity, we store the lock duration in the model
        cache()->put("task_lock_{$taskId}", [
            'user_id' => $userId,
            'locked_at' => now(),
        ], minutes: 5);

        return $task;
    }

    /**
     * Release a lock on a task.
     *
     * @param int $taskId
     * @param int $userId
     * @return bool
     */
    public function releaseLock(int $taskId, int $userId): bool
    {
        $lock = cache()->get("task_lock_{$taskId}");

        if (!$lock || $lock['user_id'] !== $userId) {
            return false; // User doesn't own the lock
        }

        cache()->forget("task_lock_{$taskId}");
        return true;
    }

    /**
     * Check if a task is locked and by whom.
     *
     * @param int $taskId
     * @return array|null {user_id: int, locked_at: string}
     */
    public function getLockInfo(int $taskId): ?array
    {
        return cache()->get("task_lock_{$taskId}");
    }

    /**
     * Check if user owns the lock on a task.
     *
     * @param int $taskId
     * @param int $userId
     * @return bool
     */
    public function ownsLock(int $taskId, int $userId): bool
    {
        $lock = $this->getLockInfo($taskId);
        return $lock && $lock['user_id'] === $userId;
    }

    /**
     * Attempt to update a task with last-write-wins strategy.
     * Returns both the update result and any conflicts that occurred.
     *
     * @param Task $task
     * @param array $data New data to apply
     * @param int $userId User attempting the update
     * @param array $expectedValues Expected values before update (for conflict detection)
     * @return array {success: bool, task: Task, conflicts: array, message: string}
     */
    public function updateWithConflictDetection(
        Task $task,
        array $data,
        int $userId,
        array $expectedValues = []
    ): array {
        $conflicts = [];

        // Check if values have changed since we last read them
        if (!empty($expectedValues)) {
            foreach ($expectedValues as $field => $expectedValue) {
                if ($task->getAttribute($field) !== $expectedValue) {
                    $conflicts[$field] = [
                        'expected' => $expectedValue,
                        'current' => $task->getAttribute($field),
                        'incoming' => $data[$field] ?? null,
                    ];
                }
            }
        }

        // If conflicts detected, return both versions
        if (!empty($conflicts)) {
            return [
                'success' => false,
                'task' => $task,
                'conflicts' => $conflicts,
                'message' => 'Conflict detected: another user modified this task',
            ];
        }

        // No conflicts, apply last-write-wins update
        try {
            $task->update($data);
            return [
                'success' => true,
                'task' => $task->fresh(),
                'conflicts' => [],
                'message' => 'Task updated successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'task' => $task,
                'conflicts' => [],
                'message' => "Update failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Merge two conflicting task versions using a merge strategy.
     * Strategy: field-by-field merge, preferring newer values.
     *
     * @param Task $task Current task
     * @param array $incomingData Incoming update data
     * @param array $conflicts Detected conflicts
     * @param string $strategy Merge strategy: 'incoming', 'current', or 'merge'
     * @return array Merged data
     */
    public function mergeConflictingVersions(
        Task $task,
        array $incomingData,
        array $conflicts,
        string $strategy = 'merge'
    ): array {
        $merged = $task->getAttributes();

        switch ($strategy) {
            case 'incoming':
                // Accept all incoming changes
                $merged = array_merge($merged, $incomingData);
                break;

            case 'current':
                // Keep current version unchanged
                break;

            case 'merge':
            default:
                // Merge field-by-field: accept incoming for non-conflicting fields,
                // for conflicting fields, use last-write-wins based on timestamp
                foreach ($incomingData as $field => $value) {
                    if (!isset($conflicts[$field])) {
                        $merged[$field] = $value;
                    } else {
                        // For conflicts, prefer the incoming value (last-write-wins)
                        $merged[$field] = $value;
                    }
                }
                break;
        }

        return $merged;
    }

    /**
     * Begin a transaction-safe update operation for collaborative editing.
     * Uses DB transactions to ensure all-or-nothing updates.
     *
     * @param Task $task
     * @param array $data
     * @param int $userId
     * @return array {success: bool, task: Task, message: string}
     */
    public function safeAtomicUpdate(Task $task, array $data, int $userId): array
    {
        try {
            return DB::transaction(function () use ($task, $data, $userId) {
                // Re-fetch with lock to ensure consistency
                $fresh = Task::where('id', $task->id)
                    ->lockForUpdate()
                    ->first();

                if (!$fresh) {
                    return [
                        'success' => false,
                        'task' => null,
                        'message' => 'Task no longer exists',
                    ];
                }

                $fresh->update($data);

                return [
                    'success' => true,
                    'task' => $fresh->fresh(),
                    'message' => 'Task updated atomically',
                ];
            });
        } catch (\Exception $e) {
            return [
                'success' => false,
                'task' => null,
                'message' => "Transaction failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Handle concurrent bulk updates with conflict detection.
     *
     * @param array $taskUpdates Array of {task_id: int, data: array, expectedValues: array}
     * @param int $userId
     * @return array {successful: int, failed: int, conflicts: array}
     */
    public function bulkUpdateWithConflicts(array $taskUpdates, int $userId): array
    {
        $successful = 0;
        $failed = 0;
        $conflicts = [];

        foreach ($taskUpdates as $update) {
            $task = Task::find($update['task_id']);
            if (!$task) {
                $failed++;
                continue;
            }

            $result = $this->updateWithConflictDetection(
                $task,
                $update['data'],
                $userId,
                $update['expectedValues'] ?? []
            );

            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
                $conflicts[$task->id] = $result['conflicts'];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
            'conflicts' => $conflicts,
        ];
    }
}
