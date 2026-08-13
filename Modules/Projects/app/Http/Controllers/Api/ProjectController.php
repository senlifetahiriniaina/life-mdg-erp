<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Models\Project;

/**
 * @group Projects - Project
 *
 * Manage projects, milestones and budgets.
 */
class ProjectController extends Controller
{
    /**
     * List projects
     *
     * Returns a paginated list of projects ordered newest first (page size fixed at 25).
     *
     * @queryParam status string Filter by status (draft, active, on_hold, completed, cancelled). Example: active
     * @queryParam owner_id integer Filter by owner user ID. Example: 1
     *
     * @response 200 scenario="Success" {"data": [{"id": 1, "name": "Website Redesign", "status": "active", "is_billable": true}], "meta": {"current_page": 1, "total": 5}}
     */
    public function index(Request $request): JsonResponse
    {
        $query = Project::with('owner')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->owner_id, fn ($q, $v) => $q->where('owner_id', $v));

        return response()->json($query->latest()->paginate(25));
    }

    /**
     * Create project
     *
     * Creates a new project. The authenticated user is automatically set as the owner.
     *
     * @bodyParam name string required Project name. Example: Website Redesign
     * @bodyParam code string Unique short code for the project (max 50 chars). Example: WEB-2025
     * @bodyParam description string Project description. Example: Full redesign of the company website.
     * @bodyParam status string Status (draft, active, on_hold, completed, cancelled). Example: draft
     * @bodyParam start_date date Project start date (Y-m-d). Example: 2025-06-01
     * @bodyParam end_date date Project end date (Y-m-d). Example: 2025-12-31
     * @bodyParam budget number Estimated project budget. Example: 50000.00
     * @bodyParam currency string ISO 4217 currency code. Example: USD
     * @bodyParam is_billable boolean Whether the project is billable to a client. Example: true
     *
     * @response 201 scenario="Created" {"id": 1, "name": "Website Redesign", "status": "draft", "budget": 50000.00, "is_billable": true, "owner": {}}
     * @response 422 scenario="Validation error" {"message": "The name field is required.", "errors": {"name": ["The name field is required."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:prj_projects,code'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,on_hold,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_billable' => ['nullable', 'boolean'],
        ]);

        $project = Project::create(array_merge($validated, [
            'owner_id' => $request->user()->id,
        ]));

        return response()->json($project->load('owner'), 201);
    }

    /**
     * Get project
     *
     * Returns a single project with its owner, members, milestones and a count of associated tasks.
     *
     * @urlParam project int required The project ID. Example: 1
     *
     * @response 200 scenario="Success" {"id": 1, "name": "Website Redesign", "status": "active", "owner": {}, "members": [], "milestones": [], "tasks_count": 12}
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(
            $project->load('owner', 'members', 'milestones')
                ->loadCount('tasks')
        );
    }

    /**
     * Update project
     *
     * Updates an existing project. All fields are optional (PATCH semantics).
     * Requires ownership or admin/manager role.
     *
     * @urlParam project int required The project ID. Example: 1
     *
     * @bodyParam name string Project name. Example: Website Redesign v2
     * @bodyParam code string Unique short code (max 50 chars). Example: WEB-2025
     * @bodyParam description string Project description. Example: Updated scope.
     * @bodyParam status string Status (draft, active, on_hold, completed, cancelled). Example: active
     * @bodyParam start_date date Project start date (Y-m-d). Example: 2025-06-01
     * @bodyParam end_date date Project end date (Y-m-d). Example: 2025-12-31
     * @bodyParam budget number Estimated project budget. Example: 60000.00
     * @bodyParam currency string ISO 4217 currency code. Example: USD
     * @bodyParam is_billable boolean Whether the project is billable. Example: true
     *
     * @response 200 scenario="Updated" {"id": 1, "name": "Website Redesign v2", "status": "active", "budget": 60000.00}
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 422 scenario="Validation error" {"message": "The status must be one of: draft, active, on_hold, completed, cancelled.", "errors": {"status": ["The status must be one of: draft, active, on_hold, completed, cancelled."]}}
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:prj_projects,code,'.$project->id],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,on_hold,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_billable' => ['nullable', 'boolean'],
        ]);

        $project->update($validated);

        return response()->json($project->fresh('owner'));
    }

    /**
     * Delete project
     *
     * Permanently deletes a project. Requires ownership or admin/manager role.
     *
     * @urlParam project int required The project ID. Example: 1
     *
     * @response 204 scenario="Deleted"
     * @response 403 scenario="Unauthorized" {"message": "This action is unauthorized."}
     * @response 404 scenario="Not found" {"message": "Not found."}
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return response()->json(null, 204);
    }
}
