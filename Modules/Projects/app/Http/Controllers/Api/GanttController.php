<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TaskDependency;
use Modules\Projects\Services\GanttService;
use Modules\Projects\Services\DependencyCycleDetectionService;

/**
 * @group Controllers - Gantt
 *
 * Manage Gantt resources.
 */
class GanttController extends Controller
{
    public function __construct(
        private readonly GanttService $ganttService,
        private readonly DependencyCycleDetectionService $cycleService,
    ) {}

    /**
     * GET /api/v1/projects/{project}/gantt
     * Full Gantt data: tasks (topologically sorted) + dependencies + critical path
     */
    public function show(Project $project): JsonResponse
    {
        return response()->json($this->ganttService->buildGanttData($project));
    }

    /**
     * POST /api/v1/projects/tasks/{task}/dependencies
     * Add a dependency to a task
     */
    public function addDependency(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'depends_on_task_id' => ['required', 'integer', 'exists:prj_tasks,id', 'different:task_id'],
            'type' => ['nullable', 'string', 'in:FS,SS,FF,SF'],
            'lag_days' => ['nullable', 'integer', 'min:-30', 'max:365'],
        ]);

        // Prevent self-reference
        if ((int) $validated['depends_on_task_id'] === $task->id) {
            return response()->json(['message' => 'A task cannot depend on itself.'], 422);
        }

        $dependsOnTask = Task::findOrFail($validated['depends_on_task_id']);
        $cycleCheck = $this->cycleService->checkCycleOnAdd($task, $dependsOnTask);
        if ($cycleCheck['cycle']) {
            return response()->json(['message' => $cycleCheck['message']], 422);
        }

        $dependency = TaskDependency::firstOrCreate(
            [
                'task_id' => $task->id,
                'depends_on_task_id' => $validated['depends_on_task_id'],
            ],
            [
                'type' => $validated['type'] ?? 'FS',
                'lag_days' => $validated['lag_days'] ?? 0,
            ]
        );

        return response()->json($dependency, 201);
    }

    /**
     * DELETE /api/v1/projects/tasks/{task}/dependencies/{dependency}
     * Remove a dependency from a task
     */
    public function removeDependency(Task $task, TaskDependency $dependency): JsonResponse
    {
        if ($dependency->task_id !== $task->id) {
            return response()->json(['message' => 'Dependency does not belong to this task.'], 403);
        }

        $dependency->delete();

        return response()->json(null, 204);
    }

    /**
     * PATCH /api/v1/projects/tasks/{task}/gantt
     * Update task start/due dates and propagate to dependents
     */
    public function updateDates(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'propagate' => ['nullable', 'boolean'],
        ]);

        $propagate = $validated['propagate'] ?? true;
        unset($validated['propagate']);

        // Calculate day delta for propagation
        $daysDelta = 0;
        if ($propagate && isset($validated['start_date']) && $task->start_date) {
            $daysDelta = (int) Carbon::parse($task->start_date)
                ->diffInDays(Carbon::parse($validated['start_date']), false);
        }

        $task->update($validated);

        if ($propagate && $daysDelta !== 0) {
            // Shift successors only (not the task itself again)
            $successorIds = TaskDependency::where('depends_on_task_id', $task->id)->pluck('task_id');
            foreach ($successorIds as $successorId) {
                $successor = Task::find($successorId);
                if ($successor) {
                    $this->ganttService->shiftTask($successor, $daysDelta);
                }
            }
        }

        $task->load('assignee:id,name');

        return response()->json([
            'id' => $task->id,
            'start_date' => $task->start_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'propagated' => $propagate && $daysDelta !== 0,
        ]);
    }
}
