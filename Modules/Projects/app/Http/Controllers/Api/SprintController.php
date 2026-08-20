<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Sprint;
use Modules\Projects\Models\Task;
use Modules\Projects\Services\SprintService;

/**
 * @group Projects - Sprints
 *
 * Sprint planning & management.
 */
class SprintController extends Controller
{
    use ScopesToProjectCompany;

    public function __construct(private readonly SprintService $sprintService) {}

    /**
     * List all sprints for a project.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $sprints = Sprint::where('project_id', $project->id)
            ->with(['tasks' => fn ($q) => $q->select('id', 'sprint_id', 'status', 'story_points')])
            ->orderBy('created_at')
            ->get()
            ->map(function (Sprint $sprint): array {
                /** @var Collection<int,Task> $tasks */
                $tasks = $sprint->tasks;
                $total = (int) $tasks->sum('story_points');
                $done = (int) $tasks->where('status', 'done')->sum('story_points');

                return array_merge($sprint->toArray(), [
                    'total_points' => $total,
                    'completed_points' => $done,
                    'task_count' => $tasks->count(),
                    'progress_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                ]);
            });

        return response()->json(['data' => $sprints]);
    }

    /**
     * Create a sprint.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'capacity_points' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        $sprint = Sprint::create(array_merge($validated, [
            'project_id' => $project->id,
            'status' => 'planning',
        ]));

        return response()->json($sprint, 201);
    }

    /**
     * Show a single sprint.
     */
    public function show(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($sprint->project_id !== $project->id) {
            abort(404);
        }

        $sprint->load('tasks');

        return response()->json($sprint);
    }

    /**
     * Update a sprint.
     */
    public function update(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($sprint->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'goal' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:planning,active,completed'],
            'capacity_points' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        $sprint->update($validated);

        return response()->json($sprint->fresh());
    }

    /**
     * Delete a sprint.
     */
    public function destroy(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($sprint->project_id !== $project->id) {
            abort(404);
        }

        $sprint->delete();

        return response()->json(null, 204);
    }

    /**
     * Start a sprint.
     * POST /projects/{project}/sprints/{sprint}/start
     */
    public function start(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($sprint->project_id !== $project->id) {
            abort(404);
        }

        $this->sprintService->startSprint($sprint);

        return response()->json($sprint->fresh());
    }

    /**
     * Complete a sprint and return incomplete tasks.
     * POST /projects/{project}/sprints/{sprint}/complete
     */
    public function complete(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($sprint->project_id !== $project->id) {
            abort(404);
        }

        $result = $this->sprintService->completeSprint($sprint);

        return response()->json($result);
    }

    /**
     * Get burndown chart data.
     * GET /projects/{project}/sprints/{sprint}/burndown
     */
    public function burndown(Request $request, Project $project, Sprint $sprint): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($sprint->project_id !== $project->id) {
            abort(404);
        }

        return response()->json($this->sprintService->getBurndown($sprint));
    }

    /**
     * Get velocity data for this project (last 5 completed sprints).
     * GET /projects/{project}/velocity
     */
    public function velocity(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        return response()->json($this->sprintService->getVelocity($project->id));
    }

    /**
     * Get backlog for this project (tasks without a sprint).
     * GET /projects/{project}/backlog
     */
    public function backlog(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $tasks = $this->sprintService->getBacklog($project->id);

        return response()->json(['data' => $tasks]);
    }
}
