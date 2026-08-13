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
}
