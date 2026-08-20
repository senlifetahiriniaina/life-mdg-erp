<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTimeLog;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TimeEntry;

/**
 * Chantier 19 Lot 2 (Projects re-verification): Project carries no global
 * tenant scope, and until this chantier only ProjectController/TaskController
 * (Chantier 10) ever verified a caller's company_id matched the record's —
 * every other Projects sub-resource controller (Milestones, Members,
 * Reports, Team, Views, Gantt, Epics, Sprints, Automations, Time Entries /
 * Time Tracking, ProjectAdvancedController's budget/kpis/risks) resolved its
 * route-bound Project/Task/time-log with zero ownership check anywhere in
 * the call chain, so any authenticated employee/manager/admin of ANY
 * company could read or write another company's project sub-resources just
 * by knowing/guessing a project id. This trait centralizes the same check
 * ProjectController::assertSameCompany() already established: 404 (not 403,
 * to avoid confirming another company's id even exists) when both sides
 * carry a real company_id and they differ; a no-op when either side is
 * null, matching this app's established graceful-degradation convention for
 * the ongoing users.company_id rollout (index()-style listing endpoints use
 * the same when()-guarded pattern directly, not this trait, since they
 * filter a query rather than assert on a single resolved model).
 */
trait ScopesToProjectCompany
{
    protected function assertSameCompanyAsProject(Request $request, Project $project): void
    {
        $userCompanyId = $request->user()?->company_id;

        if ($userCompanyId !== null && $project->company_id !== null
            && (int) $project->company_id !== (int) $userCompanyId) {
            abort(404);
        }
    }

    protected function assertSameCompanyAsTask(Request $request, Task $task): void
    {
        $userCompanyId = $request->user()?->company_id;
        $projectCompanyId = $task->project?->company_id;

        if ($userCompanyId !== null && $projectCompanyId !== null
            && (int) $projectCompanyId !== (int) $userCompanyId) {
            abort(404);
        }
    }

    protected function assertSameCompanyAsTimeLog(Request $request, ProjectTimeLog $log): void
    {
        $userCompanyId = $request->user()?->company_id;
        $projectCompanyId = $log->project?->company_id;

        if ($userCompanyId !== null && $projectCompanyId !== null
            && (int) $projectCompanyId !== (int) $userCompanyId) {
            abort(404);
        }
    }

    protected function assertSameCompanyAsTimeEntry(Request $request, TimeEntry $entry): void
    {
        $userCompanyId = $request->user()?->company_id;
        $projectCompanyId = $entry->project?->company_id;

        if ($userCompanyId !== null && $projectCompanyId !== null
            && (int) $projectCompanyId !== (int) $userCompanyId) {
            abort(404);
        }
    }

    /**
     * Resolve a Project by a plain id (routes that don't use implicit route
     * model binding, e.g. ProjectAdvancedController's `{id}` segments) and
     * assert company ownership in one step.
     */
    protected function resolveCompanyScopedProject(Request $request, int $projectId): Project
    {
        $project = Project::findOrFail($projectId);
        $this->assertSameCompanyAsProject($request, $project);

        return $project;
    }
}
