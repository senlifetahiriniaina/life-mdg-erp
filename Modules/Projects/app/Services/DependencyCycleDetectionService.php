<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Modules\Projects\Models\Task;
use Modules\Projects\Models\TaskDependency;

/**
 * Service for detecting circular dependencies in task dependency graph.
 * Uses DFS traversal with a max chain depth of 10 levels.
 */
class DependencyCycleDetectionService
{
    const MAX_CHAIN_DEPTH = 10;

    /**
     * Check if adding a dependency would create a cycle.
     *
     * @param Task $task The task that will depend on another
     * @param Task $dependsOnTask The task it will depend on
     * @return array {success: bool, cycle: bool, message: string}
     */
    public function checkCycleOnAdd(Task $task, Task $dependsOnTask): array
    {
        if ($task->id === $dependsOnTask->id) {
            return [
                'success' => false,
                'cycle' => true,
                'message' => 'A task cannot depend on itself',
            ];
        }

        // Check if dependsOnTask already depends on this task (direct cycle)
        if ($this->hasCyclicPath($dependsOnTask, $task)) {
            return [
                'success' => false,
                'cycle' => true,
                'message' => "Adding this dependency would create a circular reference: {$dependsOnTask->title} depends on {$task->title}",
            ];
        }

        return [
            'success' => true,
            'cycle' => false,
            'message' => 'No cycle detected',
        ];
    }

    /**
     * Check if there is a path from sourceTask to targetTask in the dependency graph.
     * Returns true if sourceTask depends on targetTask (directly or indirectly).
     *
     * @param Task $sourceTask The task to start searching from
     * @param Task $targetTask The task we're looking for
     * @return bool
     */
    public function hasCyclicPath(Task $sourceTask, Task $targetTask): bool
    {
        return $this->dfsHasPath($sourceTask->id, $targetTask->id, [], 0);
    }

    /**
     * DFS traversal to detect if there's a path from fromTaskId to toTaskId.
     *
     * @param int $fromTaskId Current task ID
     * @param int $toTaskId Target task ID
     * @param array $visited Set of visited task IDs
     * @param int $depth Current depth in traversal
     * @return bool
     */
    private function dfsHasPath(int $fromTaskId, int $toTaskId, array $visited, int $depth): bool
    {
        // Prevent infinite loops and enforce max depth
        if (in_array($fromTaskId, $visited) || $depth >= self::MAX_CHAIN_DEPTH) {
            return false;
        }

        $visited[] = $fromTaskId;

        // Get all tasks that $fromTaskId depends on
        $dependencies = TaskDependency::where('task_id', $fromTaskId)
            ->pluck('depends_on_task_id')
            ->toArray();

        foreach ($dependencies as $dependsOnId) {
            if ($dependsOnId === $toTaskId) {
                return true;
            }

            // Recursively check if any of its dependencies lead to target
            if ($this->dfsHasPath($dependsOnId, $toTaskId, $visited, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all dependencies for a task (both direct and transitive).
     *
     * @param Task $task
     * @return array Array of task IDs
     */
    public function getAllDependencies(Task $task): array
    {
        return $this->dfsCollectDependencies($task->id, []);
    }

    /**
     * DFS to collect all tasks that a given task depends on.
     *
     * @param int $taskId
     * @param array $visited
     * @return array
     */
    private function dfsCollectDependencies(int $taskId, array $visited): array
    {
        if (in_array($taskId, $visited)) {
            return [];
        }

        $visited[] = $taskId;
        $allDependencies = [];

        $directDependencies = TaskDependency::where('task_id', $taskId)
            ->pluck('depends_on_task_id')
            ->toArray();

        foreach ($directDependencies as $dependsOnId) {
            $allDependencies[] = $dependsOnId;
            // Recursively collect transitive dependencies
            $transitive = $this->dfsCollectDependencies($dependsOnId, $visited);
            $allDependencies = array_merge($allDependencies, $transitive);
        }

        return array_unique($allDependencies);
    }

    /**
     * Get the depth of the longest dependency chain starting from a task.
     *
     * @param Task $task
     * @return int
     */
    public function getMaxChainDepth(Task $task): int
    {
        return $this->dfsMaxDepth($task->id, []);
    }

    /**
     * DFS to find the maximum depth of dependency chain.
     *
     * @param int $taskId
     * @param array $visited
     * @return int
     */
    private function dfsMaxDepth(int $taskId, array $visited): int
    {
        if (in_array($taskId, $visited)) {
            return 0;
        }

        $visited[] = $taskId;
        $maxDepth = 0;

        $directDependencies = TaskDependency::where('task_id', $taskId)
            ->pluck('depends_on_task_id')
            ->toArray();

        foreach ($directDependencies as $dependsOnId) {
            $depth = 1 + $this->dfsMaxDepth($dependsOnId, $visited);
            $maxDepth = max($maxDepth, $depth);
        }

        return $maxDepth;
    }

    /**
     * Get critical path (longest path in the dependency graph).
     * Returns array of task IDs representing the critical path.
     *
     * @param Task $task
     * @return array
     */
    public function getCriticalPath(Task $task): array
    {
        return $this->dfsFindCriticalPath($task->id, []);
    }

    /**
     * DFS to find the longest dependency chain (critical path).
     *
     * @param int $taskId
     * @param array $visited
     * @return array
     */
    private function dfsFindCriticalPath(int $taskId, array $visited): array
    {
        if (in_array($taskId, $visited)) {
            return [$taskId];
        }

        $visited[] = $taskId;
        $longestPath = [$taskId];

        $directDependencies = TaskDependency::where('task_id', $taskId)
            ->pluck('depends_on_task_id')
            ->toArray();

        foreach ($directDependencies as $dependsOnId) {
            $path = $this->dfsFindCriticalPath($dependsOnId, $visited);
            if (count($path) + 1 > count($longestPath)) {
                $longestPath = array_merge([$taskId], $path);
            }
        }

        return $longestPath;
    }
}
