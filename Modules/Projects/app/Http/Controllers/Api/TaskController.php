<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Models\Task;

/**
 * @group Projects - Task
 *
 * Manage project tasks and sub-tasks.
 */
class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()
            ->when($request->project_id, fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->assignee_id, fn ($q, $v) => $q->where('assignee_id', $v));

        return response()->json($query->with(['project:id,name', 'assignee:id,name,email'])->latest()->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:prj_projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:todo,in_progress,review,done'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $task = Task::create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        return response()->json($task->load('project', 'assignee'), 201);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return response()->json($task->load('project', 'assignee'));
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $validated = $request->validate([
            'project_id' => ['sometimes', 'exists:prj_projects,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:todo,in_progress,review,done'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $task->update($validated);

        return response()->json($task->fresh(['project', 'assignee']));
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $task->delete();

        return response()->json(null, 204);
    }
}
