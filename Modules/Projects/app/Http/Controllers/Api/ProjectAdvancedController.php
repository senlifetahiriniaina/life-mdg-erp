<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\ProjectRisk;
use Modules\Projects\Services\GanttService;
use Modules\Projects\Services\ProjectBudgetService;
use Modules\Projects\Services\ProjectKpiService;

/**
 * ProjectAdvancedController — Phase 49 advanced project management endpoints.
 *
 * Routes actually registered (routes/api.php — see the class-level comment
 * below for what was deliberately removed and why):
 *   GET    /api/v1/projects/{id}/budget        — budget + EVM
 *   GET    /api/v1/projects/{id}/kpis          — project KPIs
 *   GET    /api/v1/projects/{id}/risks         — risks
 *   GET    /api/v1/projects/portfolio/kpis     — portfolio KPIs
 *   GET    /api/v1/projects/portfolio/timeline — portfolio timeline
 *   GET    /api/v1/projects/portfolio/resources— resource heatmap
 */
class ProjectAdvancedController extends Controller
{
    use ScopesToProjectCompany;

    public function __construct(
        private readonly GanttService         $ganttService,
        private readonly ProjectBudgetService $budgetService,
        private readonly ProjectKpiService    $kpiService,
    ) {}

    // -------------------------------------------------------------------------
    // Chantier 10: index()/store()/show()/gantt()/storeTask()/updateTask()
    // were deleted from this controller. routes/api.php's own comment already
    // documented they were deliberately never routed (they collide with the
    // real, live ProjectController/TaskController/GanttController on the
    // same paths), so they were 100% dead code — and dead code, not just
    // redundant: store() wrote tenant_id from
    // $request->user()?->tenant_id ?? $request->header('X-Tenant-ID', 1), the
    // same client-controlled-header IDOR pattern already fixed elsewhere in
    // this app (Setup's original 8.5sv vulnerability), and index()'s
    // tenant_id filter was entirely client-supplied with no scoping to the
    // caller's own company at all. Left as dead code it was a landmine for
    // whoever next un-collided the routes without re-auditing it; deleted
    // instead, matching this session's established dead-code-with-real-bugs
    // precedent (CRM's TerritoryManagementController, Logistics' wh_*/lgx_*,
    // Achats' PurchaseApprovalChainService, Workflow's legacy route block).
    // The real CRUD (ProjectController::index/store/show/update/destroy,
    // TaskController::store/update) is unaffected and remains the only path.
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Budget & EVM
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects/{id}/budget
     *
     * Chantier 19 Lot 2: this method (and kpis()/risks() below) took a
     * bare int $id and passed it straight to the service layer with ZERO
     * company-ownership check anywhere in the call chain —
     * ProjectBudgetService::getBudgetSummary()/computeEarnedValue()/
     * checkBudgetAlerts()/getBurndownData() and ProjectKpiService's
     * per-project methods all filter only by project_id, never company_id
     * (unlike their own portfolio-level siblings, which Chantier 10 did
     * scope correctly). Confirmed via code read that any authenticated
     * employee/manager/admin of ANY company could read another company's
     * project budget/EVM/KPI/risk data just by guessing a project id — a
     * real cross-tenant leak, not a hypothetical one, and more severe than
     * the identical-shaped gap already fixed on ProjectController/
     * TaskController in Chantier 10. Fixed by resolving+asserting company
     * ownership before calling the service layer, matching this app's
     * established 404-not-403 (avoid confirming another company's project
     * id exists) convention.
     */
    public function budget(Request $request, int $id): JsonResponse
    {
        $this->resolveCompanyScopedProject($request, $id);

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
    public function kpis(Request $request, int $id): JsonResponse
    {
        $this->resolveCompanyScopedProject($request, $id);

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
    public function risks(Request $request, int $id): JsonResponse
    {
        $this->resolveCompanyScopedProject($request, $id);

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
    // Chantier 10: storeTask()/updateTask() were deleted — beyond being
    // unreachable dead code (see the class-level note above), both called
    // ProjectTask::create()/ProjectTask::where(...), a model Chantier 8.4
    // already deleted outright ("100% redundant — duplicate of Task"). These
    // two methods were referencing a class that has not existed in this repo
    // since that chantier — guaranteed fatal Error::class-not-found, not
    // just unreachable. The real path is TaskController::store()/update()
    // (project-scoped via projects/{project}/tasks and tasks/{task}).
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Portfolio
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/projects/portfolio/kpis
     */
    public function portfolioKpis(Request $request): JsonResponse
    {
        // Chantier 10: was $request->user()?->tenant_id ?? 1 with a
        // client-controlled ?company_id= query override on top — any
        // authenticated user could pass ?company_id=<victim> to read another
        // company's portfolio KPIs. tenant_id is also the well-documented
        // phantom column (never populated by the real registration flow).
        // Dropped the query override; company_id (the real tenant boundary,
        // now a real column on prj_projects — see the Chantier 10 migration)
        // is the only source.
        $companyId = (int) ($request->user()?->company_id ?? 0);
        $kpis      = $this->kpiService->getPortfolioKpis($companyId);

        return response()->json(['data' => $kpis]);
    }

    /**
     * GET /api/v1/projects/portfolio/timeline
     *
     * Chantier 10: GanttService::getTimelineOverview() did not exist —
     * guaranteed fatal Error on every call to this routed endpoint, not a
     * hypothetical gap. Built for real, scoped to the caller's own company's
     * active projects, using the same real schedule-performance shape
     * ProjectKpiService already computes per project.
     */
    public function portfolioTimeline(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);
        $data      = $this->ganttService->getTimelineOverview($companyId);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/projects/portfolio/resources
     *
     * Chantier 10: GanttService::getResourceHeatmap() did not exist —
     * guaranteed fatal Error on every call to this routed endpoint, not a
     * hypothetical gap. Built for real: per-member logged-vs-capacity hours
     * across the caller's own company's active projects, using the same
     * team-member/timesheet tables ProjectKpiService::getTeamUtilization()
     * already reads for a single project.
     */
    public function portfolioResources(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);
        $data      = $this->ganttService->getResourceHeatmap($companyId);

        return response()->json(['data' => $data]);
    }
}
