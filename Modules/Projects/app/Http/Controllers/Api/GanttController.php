<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
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
    use ScopesToProjectCompany;

    public function __construct(
        private readonly GanttService $ganttService,
        private readonly DependencyCycleDetectionService $cycleService,
    ) {}

    /**
     * GET /api/v1/projects/{project}/gantt
     * Full Gantt data: tasks (topologically sorted) + dependencies + critical path
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        return response()->json($this->ganttService->buildGanttData($project));
    }

    /**
     * POST /api/v1/projects/tasks/{task}/dependencies
     * Add a dependency to a task
     */
    public function addDependency(Request $request, Task $task): JsonResponse
    {
        $this->assertSameCompanyAsTask($request, $task);

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

        // Chantier 32.17 (14-layer deep audit): neither the request
        // validation ('exists:prj_tasks,id' has no project scope) nor
        // TaskDependency::booted()'s creating() cycle-check verified that
        // $dependsOnTask actually belongs to the same project as $task — a
        // real cross-tenant IDOR (create a dependency edge from your own
        // company's task onto ANY other company's task by id, then
        // GanttController::updateDates()'s propagate-to-successors logic
        // would silently shift the foreign task's dates on every date
        // update to your own task). Dependencies are inherently
        // project-scoped in this schema (Gantt/critical-path are computed
        // per-project) — same-project also implies same-company since
        // Project::company_id is the tenant boundary.
        if ($dependsOnTask->project_id !== $task->project_id) {
            return response()->json(['message' => 'A task can only depend on another task in the same project.'], 422);
        }

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
    public function removeDependency(Request $request, Task $task, TaskDependency $dependency): JsonResponse
    {
        $this->assertSameCompanyAsTask($request, $task);

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
        $this->assertSameCompanyAsTask($request, $task);

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
