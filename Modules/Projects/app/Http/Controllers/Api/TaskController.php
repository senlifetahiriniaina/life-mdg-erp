<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Projects\Services\AutomationService;
use Modules\Projects\Services\TaskConflictResolutionService;

/**
 * @group Projects - Task
 *
 * Manage project tasks and sub-tasks.
 */
class TaskController extends Controller
{
    use ScopesToProjectCompany;

    public function __construct(
        private readonly AutomationService $automation,
        private readonly TaskConflictResolutionService $conflicts,
    ) {}

    /**
     * Chantier 32.17 (14-layer deep audit): both routes below are, and have
     * always been, registered ONLY as `projects/{project}/tasks` (there is
     * no bare `/tasks` list/create route anywhere in this module) — yet
     * neither index() nor store() ever type-hinted the route-bound
     * `Project $project` at all, filtering purely off a separate
     * `?project_id=`/body `project_id` the caller had to also supply. This
     * silently broke the one real caller: Epics/Index.vue's addStory()
     * posts to `/api/v1/projects/{pid}/tasks` with NO project_id in the
     * body at all (relying, correctly, on the URL) — every real "add task
     * to epic" click 422'd with "The project id field is required.",
     * confirmed empirically. Fixed by using the route param as the single
     * source of truth, matching every other Projects sub-resource
     * controller's own established convention.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $query = Task::query()
            ->where('project_id', $project->id)
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->assignee_id, fn ($q, $v) => $q->where('assignee_id', $v));

        return response()->json($query->with(['project:id,name', 'assignee:id,name,email'])->latest()->paginate(25));
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:todo,in_progress,review,done'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'max:50'],
            'story_points' => ['nullable', 'integer', 'min:0'],
            'epic_id' => ['nullable', 'exists:prj_epics,id'],
            'sprint_id' => ['nullable', 'exists:prj_sprints,id'],
            'milestone_id' => ['nullable', 'exists:prj_milestones,id'],
            'parent_id' => ['nullable', 'exists:prj_tasks,id'],
        ]);

        $task = Task::create(array_merge($validated, [
            'project_id' => $project->id,
            'created_by' => $request->user()->id,
        ]));

        // Chantier 32.17 (14-layer deep audit): AutomationService::evaluate()
        // is real, tested, well-written business logic (matches rules
        // against the 'trigger' AutomationController::store() itself
        // validates: task_created/task_status_changed/task_assigned/
        // comment_added) but had ZERO producer anywhere in the app — a
        // caller could build automation rules through the real, wired
        // AutomationController CRUD and they would simply never fire, no
        // matter what happened. Wired the 3 real triggers this module can
        // actually produce (task_created here; task_status_changed/
        // task_assigned in update() below) — comment_added has no producer
        // since this module has no task-comment concept of its own to wire,
        // left as a real but currently-unfireable trigger type, same as
        // before this fix, just now honestly matching what the other 3
        // triggers already do.
        $this->automation->evaluate('task_created', [
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'status' => $task->status,
            'assignee_id' => $task->assignee_id,
            'priority' => $task->priority,
        ]);

        return response()->json($task->load('project', 'assignee'), 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);
        $this->assertSameCompanyAsTask($request, $task);

        return response()->json($task->load('project', 'assignee'));
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $this->assertSameCompanyAsTask($request, $task);
        $validated = $request->validate([
            'project_id' => ['sometimes', 'exists:prj_projects,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:todo,in_progress,review,done'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $statusChanged = array_key_exists('status', $validated) && $validated['status'] !== $task->status;
        $assigneeChanged = array_key_exists('assignee_id', $validated) && $validated['assignee_id'] !== $task->assignee_id;

        $task->update($validated);

        $payload = [
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'status' => $task->status,
            'assignee_id' => $task->assignee_id,
            'priority' => $task->priority,
        ];
        if ($statusChanged) {
            $this->automation->evaluate('task_status_changed', $payload);
        }
        if ($assigneeChanged) {
            $this->automation->evaluate('task_assigned', $payload);
        }

        return response()->json($task->fresh(['project', 'assignee']));
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $this->assertSameCompanyAsTask($request, $task);
        $task->delete();

        return response()->json(null, 204);
    }

    /**
     * Chantier 32.17 (14-layer deep audit, layer 9 fake/dead):
     * TaskConflictResolutionService is real, tested (16/16 passing per this
     * app's own tracked history — see CLAUDE.md's Chantier 9 entry), and
     * genuinely self-contained (only imports DB/Task, zero dependency on
     * the excluded RealTime/Discussion modules) — a real pessimistic-lock
     * + last-write-wins collaborative-editing engine that had never had a
     * single route anywhere in this app. Classified "activate": exposes the
     * lock lifecycle for real (any real-time UI can be built against it
     * later — that UI is out of this audit's scope, matching the API-First
     * precedent of shipping the real endpoint before the frontend exists).
     */
    public function lock(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $this->assertSameCompanyAsTask($request, $task);

        $locked = $this->conflicts->acquireLock($task->id, $request->user()->id);
        if ($locked === null) {
            return response()->json(['message' => 'Task not found.'], 404);
        }

        return response()->json(['data' => $this->conflicts->getLockInfo($task->id)], 201);
    }

    public function unlock(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $this->assertSameCompanyAsTask($request, $task);

        $released = $this->conflicts->releaseLock($task->id, $request->user()->id);

        return response()->json(['released' => $released]);
    }

    public function lockInfo(Request $request, Task $task): JsonResponse
    {
        $this->assertSameCompanyAsTask($request, $task);

        return response()->json(['data' => $this->conflicts->getLockInfo($task->id)]);
    }

    // assertSameCompanyAsTask() now lives on the shared
    // Concerns\ScopesToProjectCompany trait (Chantier 19 Lot 2) — every
    // other Projects sub-resource controller needed the identical check.
}
