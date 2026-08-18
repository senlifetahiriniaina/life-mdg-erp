<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Projects\Models\Project;

class ProjectWebController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = Project::query()
            ->with(['owner'])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            }))
            ->paginate(25)->withQueryString();

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function show(Project $project): Response
    {
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
    public function calendar(Project $project): Response
    {
        return Inertia::render('Projects/Calendar', ['project' => $project]);
    }

    public function gantt(Project $project): Response
    {
        return Inertia::render('Projects/Gantt', ['project' => $project]);
    }

    public function kanban(Project $project): Response
    {
        return Inertia::render('Projects/Kanban', ['project' => $project]);
    }

    /**
     * Automation/Epics/Sprints.vue are real, fully-built pages (real axios
     * CRUD calls against already-live automations/epics/sprints endpoints)
     * that accept an optional `projectId` — pass the real project id so
     * they load scoped to it rather than rendering with nothing to fetch.
     */
    public function automation(Project $project): Response
    {
        return Inertia::render('Projects/Automation/Index', ['projectId' => $project->id]);
    }

    public function epics(Project $project): Response
    {
        return Inertia::render('Projects/Epics/Index', ['projectId' => $project->id]);
    }

    public function sprints(Project $project): Response
    {
        return Inertia::render('Projects/Sprints/Index', ['projectId' => $project->id]);
    }

    /**
     * Roadmap.vue is cross-project (optional `projects` list for its
     * project-filter dropdown) — it already self-fetches epics/sprints via
     * axios, so only a lightweight id/name list is needed here.
     */
    public function roadmap(): Response
    {
        return Inertia::render('Projects/Roadmap', [
            'projects' => Project::query()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }
}
