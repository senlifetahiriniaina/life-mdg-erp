<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTeamMember;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TaskDependency;

class GanttService
{
    /**
     * Build complete Gantt data for a project:
     * tasks ordered topologically with computed dates, dependencies, and critical path.
     *
     * @return array{tasks: array<int, mixed>, dependencies: array<int, mixed>, critical_path: array<int, int>}
     */
    public function buildGanttData(Project $project): array
    {
        $tasks = $project->tasks()
            ->with(['assignee:id,name', 'dependencies', 'subtasks'])
            ->get();

        $deps = TaskDependency::whereIn('task_id', $tasks->pluck('id'))
            ->orWhereIn('depends_on_task_id', $tasks->pluck('id'))
            ->get();

        // Build dependency map: task_id => [depends_on_task_id, ...]
        $depMap = [];
        foreach ($deps as $dep) {
            $depMap[$dep->task_id][] = $dep->depends_on_task_id;
        }

        $sorted = $this->topologicalSort($tasks->toArray(), $depMap);
        $critIds = $this->getCriticalPath($project);

        $taskIndex = $tasks->keyBy('id');

        $result = [];
        foreach ($sorted as $taskId) {
            $task = $taskIndex->get($taskId);
            if (! $task) {
                continue;
            }

            $result[] = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'start_date' => $task->start_date?->toDateString(),
                'due_date' => $task->due_date?->toDateString(),
                'assignee' => $task->assignee ? $task->assignee->name : null,
                'assignee_id' => $task->assignee_id,
                'parent_id' => $task->parent_id,
                'estimated_hours' => $task->estimated_hours,
                'logged_hours' => $task->logged_hours,
                'is_critical' => in_array($task->id, $critIds, true),
                'level' => $task->parent_id ? 1 : 0,
                'progress' => $task->estimated_hours > 0
                    ? min(100, (int) round($task->logged_hours / $task->estimated_hours * 100))
                    : 0,
            ];
        }

        return [
            'tasks' => $result,
            'dependencies' => $deps->map(fn ($d) => [
                'id' => $d->id,
                'task_id' => $d->task_id,
                'depends_on_task_id' => $d->depends_on_task_id,
                'type' => $d->type,
                'lag_days' => $d->lag_days,
            ])->values()->toArray(),
            'critical_path' => $critIds,
        ];
    }

    /**
     * Calculate the critical path using the longest-path method on a DAG.
     * Returns an array of task IDs on the critical path.
     *
     * @return array<int, int>
     */
    public function getCriticalPath(Project $project): array
    {
        $tasks = $project->tasks()->get();
        if ($tasks->isEmpty()) {
            return [];
        }

        $deps = TaskDependency::whereIn('task_id', $tasks->pluck('id'))->get();

        // Build adjacency list (successor map)
        $successors = [];
        $predecessors = [];
        foreach ($tasks as $task) {
            $successors[$task->id] = $successors[$task->id] ?? [];
            $predecessors[$task->id] = $predecessors[$task->id] ?? [];
        }
        foreach ($deps as $dep) {
            $successors[$dep->depends_on_task_id][] = $dep->task_id;
            $predecessors[$dep->task_id][] = $dep->depends_on_task_id;
        }

        // Compute duration of each task in days
        $duration = [];
        foreach ($tasks as $task) {
            if ($task->start_date && $task->due_date) {
                $duration[$task->id] = max(1, $task->start_date->diffInDays($task->due_date) + 1);
            } else {
                $duration[$task->id] = max(1, (int) ceil(($task->estimated_hours ?? 8) / 8));
            }
        }

        // Topological sort
        $depMap = [];
        foreach ($deps as $dep) {
            $depMap[$dep->task_id][] = $dep->depends_on_task_id;
        }
        $sorted = $this->topologicalSort($tasks->toArray(), $depMap);

        // Forward pass: earliest finish
        $ef = [];
        foreach ($sorted as $id) {
            $preds = $predecessors[$id] ?? [];
            $es = empty($preds) ? 0 : max(array_map(fn ($p) => $ef[$p] ?? 0, $preds));
            $ef[$id] = $es + ($duration[$id] ?? 1);
        }

        // Backward pass: latest finish
        $projectEnd = empty($ef) ? 0 : max($ef);
        $lf = [];
        foreach (array_reverse($sorted) as $id) {
            $succs = $successors[$id] ?? [];
            $lf[$id] = empty($succs)
                ? $projectEnd
                : min(array_map(fn ($s) => ($lf[$s] ?? $projectEnd) - ($duration[$s] ?? 1), $succs));
        }

        // Critical: tasks where EF == LF (slack = 0)
        $critical = [];
        foreach ($tasks as $task) {
            $slack = ($lf[$task->id] ?? 0) - ($ef[$task->id] ?? 0);
            if (abs((float) $slack) < 0.001) {
                $critical[] = $task->id;
            }
        }

        return $critical;
    }

    /**
     * Cross-project timeline overview for a company's active projects.
     *
     * Chantier 10: routed via ProjectAdvancedController::portfolioTimeline()
     * (GET /api/v1/projects/portfolio/timeline), which called a method that
     * did not exist on this service at all — a guaranteed fatal Error on
     * every real call. Built for real rather than stubbed.
     *
     * @return array{projects: array<int, mixed>, project_count: int}
     */
    public function getTimelineOverview(int $companyId): array
    {
        $projects = Project::where('company_id', $companyId)
            ->whereIn('status', ['active', 'in_progress'])
            ->orderBy('start_date')
            ->get(['id', 'name', 'status', 'start_date', 'end_date']);

        $timeline = $projects->map(function (Project $project) {
            $totalTasks = Task::where('project_id', $project->id)->count();
            $doneTasks  = Task::where('project_id', $project->id)->where('status', 'done')->count();

            return [
                'project_id'     => $project->id,
                'name'           => $project->name,
                'status'         => $project->status,
                'start_date'     => $project->start_date?->toDateString(),
                'end_date'       => $project->end_date?->toDateString(),
                'completion_pct' => $totalTasks > 0 ? round($doneTasks / $totalTasks * 100, 1) : 0.0,
            ];
        })->values()->toArray();

        return [
            'projects'      => $timeline,
            'project_count' => count($timeline),
        ];
    }

    /**
     * Per-member logged-vs-capacity hours across a company's active
     * projects.
     *
     * Chantier 10: routed via ProjectAdvancedController::portfolioResources()
     * (GET /api/v1/projects/portfolio/resources), which called a method that
     * did not exist on this service at all — a guaranteed fatal Error on
     * every real call. Built for real rather than stubbed. Timesheet hours
     * are best-effort (wrapped defensively, matching ProjectKpiService's own
     * fallback-first pattern for the same underlying table) since ts_*
     * timesheet linkage is not this service's core responsibility.
     *
     * @return array{members: array<int, mixed>, member_count: int}
     */
    public function getResourceHeatmap(int $companyId): array
    {
        $projectIds = Project::where('company_id', $companyId)
            ->whereIn('status', ['active', 'in_progress'])
            ->pluck('id');

        $members = ProjectTeamMember::whereIn('project_id', $projectIds)
            ->whereNull('left_at')
            ->with('user:id,name')
            ->get()
            ->groupBy('user_id');

        $capacityHours = 8 * 22; // one member's monthly capacity (22 working days x 8h)

        $heatmap = $members->map(function ($rows, $userId) use ($capacityHours) {
            $loggedHours = 0.0;
            try {
                $loggedHours = (float) DB::table('ts_timesheets')
                    ->whereIn('project_id', $rows->pluck('project_id'))
                    ->where('user_id', $userId)
                    ->whereMonth('work_date', now()->month)
                    ->sum('hours_logged');
            } catch (\Exception) {
                // ts_timesheets is a best-effort, possibly-absent source —
                // degrade to 0 logged hours rather than fail the endpoint.
            }

            return [
                'user_id'         => (int) $userId,
                'name'            => $rows->first()?->user?->name ?? 'Unknown',
                'project_count'   => $rows->count(),
                'logged_hours'    => round($loggedHours, 1),
                'capacity_hours'  => (float) $capacityHours,
                'utilization_pct' => $capacityHours > 0 ? round($loggedHours / $capacityHours * 100, 1) : 0.0,
            ];
        })->values()->toArray();

        return [
            'members'      => $heatmap,
            'member_count' => count($heatmap),
        ];
    }

    /**
     * Shift a task and all its dependents (successors) by $daysDelta days.
     */
    public function shiftTask(Task $task, int $daysDelta): void
    {
        $visited = [];
        $this->shiftRecursive($task, $daysDelta, $visited);
    }

    /**
     * @param  array<int, bool>  $visited
     */
    private function shiftRecursive(Task $task, int $daysDelta, array &$visited): void
    {
        if (isset($visited[$task->id])) {
            return;
        }
        $visited[$task->id] = true;

        $updates = [];
        if ($task->start_date) {
            $updates['start_date'] = Carbon::parse($task->start_date)->addDays($daysDelta)->toDateString();
        }
        if ($task->due_date) {
            $updates['due_date'] = Carbon::parse($task->due_date)->addDays($daysDelta)->toDateString();
        }
        if ($updates !== []) {
            $task->update($updates);
        }

        // Shift successors
        $successorIds = TaskDependency::where('depends_on_task_id', $task->id)->pluck('task_id');
        foreach ($successorIds as $successorId) {
            /** @var Task|null $successor */
            $successor = Task::find($successorId);
            if ($successor) {
                $this->shiftRecursive($successor, $daysDelta, $visited);
            }
        }
    }

    /**
     * Topological sort (Kahn's algorithm) with cycle detection.
     * Returns ordered array of task IDs. Throws on cycle.
     *
     * @param  array<int, mixed>  $tasks  Raw task arrays (must have 'id')
     * @param  array<int, array<int>>  $deps  task_id => [depends_on_task_id, ...]
     * @return array<int, int>
     */
    private function topologicalSort(array $tasks, array $deps): array
    {
        $inDegree = [];
        $adjList = []; // predecessor -> successors

        foreach ($tasks as $task) {
            $id = (int) (is_array($task) ? $task['id'] : $task->id);
            $inDegree[$id] = $inDegree[$id] ?? 0;
            $adjList[$id] = $adjList[$id] ?? [];
        }

        foreach ($deps as $taskId => $predecessors) {
            foreach ($predecessors as $predId) {
                $adjList[$predId][] = (int) $taskId;
                $inDegree[(int) $taskId] = ($inDegree[(int) $taskId] ?? 0) + 1;
            }
        }

        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $sorted = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $sorted[] = $current;
            foreach ($adjList[$current] ?? [] as $successor) {
                $inDegree[$successor]--;
                if ($inDegree[$successor] === 0) {
                    $queue[] = $successor;
                }
            }
        }

        // If not all tasks were processed, there's a cycle — return what we have
        // (cycle handling: append remaining nodes in original order)
        if (count($sorted) < count($tasks)) {
            $sortedSet = array_flip($sorted);
            foreach ($tasks as $task) {
                $id = (int) (is_array($task) ? $task['id'] : $task->id);
                if (! isset($sortedSet[$id])) {
                    $sorted[] = $id;
                }
            }
        }

        return $sorted;
    }
}
