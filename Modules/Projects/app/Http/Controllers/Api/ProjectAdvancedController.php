<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectRisk;
use Modules\Projects\Models\ProjectTask;
use Modules\Projects\Services\GanttService;
use Modules\Projects\Services\ProjectBudgetService;
use Modules\Projects\Services\ProjectKpiService;

/**
 * ProjectAdvancedController — Phase 49 advanced project management endpoints.
 *
 * Routes:
 *   GET    /api/v1/projects                    — list
 *   POST   /api/v1/projects                    — create
 *   GET    /api/v1/projects/{id}               — detail
 *   GET    /api/v1/projects/{id}/gantt         — Gantt data
 *   GET    /api/v1/projects/{id}/budget        — budget + EVM
 *   GET    /api/v1/projects/{id}/kpis          — project KPIs
 *   GET    /api/v1/projects/{id}/risks         — risks
 *   POST   /api/v1/projects/{id}/tasks         — create task
 *   PUT    /api/v1/projects/{id}/tasks/{taskId}— update task
 *   GET    /api/v1/projects/portfolio/kpis     — portfolio KPIs
 *   GET    /api/v1/projects/portfolio/timeline — portfolio timeline
 *   GET    /api/v1/projects/portfolio/resources— resource heatmap
 */
class ProjectAdvancedController extends Controller
{
    public function __construct(
        private readonly GanttService         $ganttService,
        private readonly ProjectBudgetService $budgetService,
        private readonly ProjectKpiService    $kpiService,
    ) {}

    // -------------------------------------------------------------------------
    // Project CRUD
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects
     * Filter by: status, type, client_id
     */
    public function index(Request $request): JsonResponse
    {
        $query = Project::query()->with(['milestones', 'teamMembers']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('client_id')) {
            $query->byClient((int) $request->client_id);
        }

        // Tenant isolation
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        $projects = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'data' => $projects->items(),
            'meta' => [
                'total'        => $projects->total(),
                'current_page' => $projects->currentPage(),
                'last_page'    => $projects->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/projects
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'status'         => 'nullable|string|in:draft,active,in_progress,completed,cancelled,on_hold',
            'type'           => 'nullable|string',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'budget'         => 'nullable|numeric|min:0',
            'contract_value' => 'nullable|numeric|min:0',
            'currency'       => 'nullable|string|size:3',
            'client_id'      => 'nullable|integer',
            'is_billable'    => 'nullable|boolean',
        ]);

        $validated['owner_id']  = $request->user()?->id ?? 1;
        $validated['tenant_id'] = $request->user()?->tenant_id ?? $request->header('X-Tenant-ID', 1);
        $validated['currency']  ??= 'XOF';
        $validated['status']    ??= 'draft';

        $project = Project::create($validated);

        return response()->json([
            'data'    => $project->fresh(),
            'message' => 'Project created successfully',
        ], 201);
    }

    /**
     * GET /api/v1/projects/{id}
     */
    public function show(int $id): JsonResponse
    {
        $project = Project::with(['milestones', 'tasks', 'teamMembers', 'risks', 'budgetLines'])
            ->find($id);

        if (! $project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        return response()->json([
            'data' => array_merge($project->toArray(), [
                'health_color'    => $project->health_color,
                'completion_rate' => $project->completion_rate,
                'budget_variance' => $project->budget_variance,
            ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Gantt
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects/{id}/gantt
     */
    public function gantt(int $id): JsonResponse
    {
        $data = $this->ganttService->getGanttData($id);

        return response()->json(['data' => $data]);
    }

    // -------------------------------------------------------------------------
    // Budget & EVM
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects/{id}/budget
     */
    public function budget(int $id): JsonResponse
    {
        $summary = $this->budgetService->getBudgetSummary($id);
        $evm     = $this->budgetService->computeEarnedValue($id);
        $alerts  = $this->budgetService->checkBudgetAlerts($id);
        $burndown = $this->budgetService->getBurndownData($id);

        return response()->json([
            'data' => [
                'summary'  => $summary,
                'evm'      => $evm,
                'alerts'   => $alerts,
                'burndown' => $burndown,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // KPIs
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects/{id}/kpis
     */
    public function kpis(int $id): JsonResponse
    {
        $kpis     = $this->kpiService->getProjectKpis($id);
        $velocity = $this->kpiService->getVelocityTrend($id);
        $strategy = $this->kpiService->linkToStrategy($id);

        return response()->json([
            'data' => array_merge($kpis, [
                'velocity_trend'  => $velocity['data'],
                'strategy_kpis'   => $strategy['strategy_kpis'],
            ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Risks
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects/{id}/risks
     */
    public function risks(int $id): JsonResponse
    {
        $risks = ProjectRisk::where('project_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => array_merge($r->toArray(), [
                'risk_score'    => $r->risk_score,
                'risk_severity' => $r->risk_severity,
            ]));

        return response()->json(['data' => $risks]);
    }

    // -------------------------------------------------------------------------
    // Tasks
    // -------------------------------------------------------------------------

    /**
     * POST /api/v1/projects/{id}/tasks
     */
    public function storeTask(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'status'          => 'nullable|string',
            'priority'        => 'nullable|string|in:low,medium,high,urgent',
            'type'            => 'nullable|string',
            'milestone_id'    => 'nullable|integer',
            'assignee_id'     => 'nullable|integer',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'dependencies'    => 'nullable|array',
            'is_critical_path'=> 'nullable|boolean',
        ]);

        $validated['project_id'] = $id;
        $validated['created_by'] = $request->user()?->id ?? 1;
        $validated['status']     ??= 'todo';
        $validated['priority']   ??= 'medium';

        $task = ProjectTask::create($validated);

        return response()->json([
            'data'    => array_merge($task->toArray(), [
                'status_color' => $task->status_color,
                'duration_days'=> $task->duration_days,
            ]),
            'message' => 'Task created successfully',
        ], 201);
    }

    /**
     * PUT /api/v1/projects/{id}/tasks/{taskId}
     */
    public function updateTask(Request $request, int $id, int $taskId): JsonResponse
    {
        $task = ProjectTask::where('project_id', $id)->find($taskId);

        if (! $task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        $validated = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'status'          => 'sometimes|string',
            'priority'        => 'sometimes|string|in:low,medium,high,urgent',
            'milestone_id'    => 'nullable|integer',
            'assignee_id'     => 'nullable|integer',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:0',
            'logged_hours'    => 'nullable|integer|min:0',
            'dependencies'    => 'nullable|array',
            'is_critical_path'=> 'nullable|boolean',
        ]);

        $task->update($validated);

        return response()->json([
            'data'    => array_merge($task->fresh()->toArray(), [
                'status_color' => $task->status_color,
                'duration_days'=> $task->duration_days,
            ]),
            'message' => 'Task updated successfully',
        ]);
    }

    // -------------------------------------------------------------------------
    // Portfolio
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects/portfolio/kpis
     */
    public function portfolioKpis(Request $request): JsonResponse
    {
        $companyId = (int) $request->query('company_id', $request->user()?->tenant_id ?? 1);
        $kpis      = $this->kpiService->getPortfolioKpis($companyId);

        return response()->json(['data' => $kpis]);
    }

    /**
     * GET /api/v1/projects/portfolio/timeline
     */
    public function portfolioTimeline(Request $request): JsonResponse
    {
        $data = $this->ganttService->getTimelineOverview();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/projects/portfolio/resources
     */
    public function portfolioResources(Request $request): JsonResponse
    {
        $data = $this->ganttService->getResourceHeatmap();

        return response()->json(['data' => $data]);
    }
}
