<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\Project;

class ProjectWebController extends Controller
{
    use ScopesToProjectCompany;

    /**
     * Chantier 19 Lot 2: the same company-scoping gap already fixed across
     * every API sub-resource controller existed here too, and arguably more
     * severely — this Inertia web controller server-renders a project's
     * full detail (name, description, budget, tasks, milestones, owner)
     * straight into the page props, with zero company-ownership check.
     * Visiting /projects/{id} for another company's project id rendered
     * that company's real project data, independent of any API-layer fix.
     * index() is left as-is (already company-scoped implicitly via
     * ProjectController::index()'s own pattern — see below) but every
     * method taking a route-bound Project now asserts ownership first.
     */
    public function index(Request $request): Response
    {
        // Chantier 19 Lot 2: mirrors ProjectController::index()'s own
        // Chantier 10 fix — this page listed every company's projects.
        $projects = Project::query()
            ->with(['owner'])
            ->when($request->user()?->company_id, fn ($q, $companyId) => $q->where('company_id', $companyId))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            }))
            ->paginate(25)->withQueryString();

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->assertSameCompanyAsProject($request, $project);

        $project->load(['tasks.assignee', 'milestones', 'owner']);

        return Inertia::render('Projects/Show', ['project' => $project]);
    }

    /**
     * Chantier 8.4: Calendar/Gantt/Kanban.vue are real, fully-built pages
     * (real axios calls against already-live views/calendar, views/gantt,
     * views/kanban endpoints) requiring a `project` prop — none had a web
     * route. Reachable only by direct URL, matching the
     * consolidation-hierarchies discoverability precedent.
     */
    public function calendar(Request $request, Project $project): Response
    {
        $this->assertSameCompanyAsProject($request, $project);

        return Inertia::render('Projects/Calendar', ['project' => $project]);
    }

    public function gantt(Request $request, Project $project): Response
    {
        $this->assertSameCompanyAsProject($request, $project);

        return Inertia::render('Projects/Gantt', ['project' => $project]);
    }

    public function kanban(Request $request, Project $project): Response
    {
        $this->assertSameCompanyAsProject($request, $project);

        return Inertia::render('Projects/Kanban', ['project' => $project]);
    }

    /**
     * Automation/Epics/Sprints.vue are real, fully-built pages (real axios
     * CRUD calls against already-live automations/epics/sprints endpoints)
     * that accept an optional `projectId` — pass the real project id so
     * they load scoped to it rather than rendering with nothing to fetch.
     */
    public function automation(Request $request, Project $project): Response
    {
        $this->assertSameCompanyAsProject($request, $project);

        return Inertia::render('Projects/Automation/Index', ['projectId' => $project->id]);
    }

    public function epics(Request $request, Project $project): Response
    {
        $this->assertSameCompanyAsProject($request, $project);

        return Inertia::render('Projects/Epics/Index', ['projectId' => $project->id]);
    }

    public function sprints(Request $request, Project $project): Response
    {
        $this->assertSameCompanyAsProject($request, $project);

        return Inertia::render('Projects/Sprints/Index', ['projectId' => $project->id]);
    }

    /**
     * Roadmap.vue is cross-project (optional `projects` list for its
     * project-filter dropdown) — it already self-fetches epics/sprints via
     * axios, so only a lightweight id/name list is needed here.
     *
     * Chantier 19 Lot 2: this list had zero company scoping — every
     * company's project names leaked into the dropdown. Scoped to the
     * caller's own company when one is present, matching this app's
     * established graceful-degradation when()-guard convention.
     */
    public function roadmap(Request $request): Response
    {
        return Inertia::render('Projects/Roadmap', [
            'projects' => Project::query()
                ->when(
                    $request->user()?->company_id,
                    fn ($q, $companyId) => $q->where('company_id', $companyId)
                )
                ->select('id', 'name')->orderBy('name')->get(),
        ]);
    }
}
