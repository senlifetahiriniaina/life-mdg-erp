<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Models\Epic;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;

/**
 * @group Projects - Epics
 *
 * Manage project epics.
 */
class EpicController extends Controller
{
    /**
     * List all epics.
     * Supports optional ?project_id filter for roadmap use.
     */
    public function all(Request $request): JsonResponse
    {
        $query = Epic::query()->with('project');

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->query('project_id'));
        }

        return response()->json(['data' => $query->orderBy('start_date')->get()]);
    }

    /**
     * List epics for a project.
     */
    public function index(Project $project): JsonResponse
    {
        $epics = Epic::where('project_id', $project->id)
            ->with(['tasks' => fn ($q) => $q->select('id', 'epic_id', 'status', 'story_points')])
            ->orderBy('start_date')
            ->get()
            ->map(function (Epic $epic): array {
                /** @var Collection<int, Task> $tasks */
                $tasks = $epic->tasks;
                $total = $tasks->count();
                $done = $tasks->where('status', 'done')->count();
                $totalPoints = $tasks->sum('story_points') ?? 0;
                $donePoints = $tasks->where('status', 'done')->sum('story_points') ?? 0;

                return array_merge($epic->toArray(), [
                    'task_count' => $total,
                    'done_count' => $done,
                    'progress_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                    'total_story_points' => (int) $totalPoints,
                    'done_story_points' => (int) $donePoints,
                ]);
            });

        return response()->json(['data' => $epics]);
    }

    /**
     * Create an epic.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'status' => ['nullable', 'string', 'in:open,in_progress,done'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $epic = Epic::create(array_merge($validated, ['project_id' => $project->id]));

        return response()->json($epic, 201);
    }

    /**
     * Get a single epic.
     */
    public function show(Project $project, Epic $epic): JsonResponse
    {
        if ($epic->project_id !== $project->id) {
            abort(404);
        }

        $epic->load(['tasks' => fn ($q) => $q->select('id', 'epic_id', 'title', 'status', 'story_points', 'assignee_id')]);

        return response()->json($epic);
    }

    /**
     * Update an epic.
     */
    public function update(Request $request, Project $project, Epic $epic): JsonResponse
    {
        if ($epic->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
            'status' => ['nullable', 'string', 'in:open,in_progress,done'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $epic->update($validated);

        return response()->json($epic->fresh());
    }

    /**
     * Delete an epic.
     */
    public function destroy(Project $project, Epic $epic): JsonResponse
    {
        if ($epic->project_id !== $project->id) {
            abort(404);
        }

        $epic->delete();

        return response()->json(null, 204);
    }
}
