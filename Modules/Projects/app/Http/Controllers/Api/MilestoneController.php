<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Models\Milestone;
use Modules\Projects\Models\Project;

/**
 * @group Projects - Milestones
 *
 * Manage project milestones.
 */
class MilestoneController extends Controller
{
    /**
     * List milestones for a project.
     *
     * @urlParam project int required The project ID. Example: 1
     */
    public function index(Project $project): JsonResponse
    {
        $milestones = Milestone::where('project_id', $project->id)
            ->orderBy('due_date')
            ->get();

        return response()->json($milestones);
    }

    /**
     * Create a milestone.
     *
     * @urlParam project int required The project ID. Example: 1
     *
     * @bodyParam name string required Milestone name. Example: Beta Release
     * @bodyParam due_date date Due date (Y-m-d). Example: 2025-06-01
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ]);

        $milestone = Milestone::create(array_merge($validated, [
            'project_id' => $project->id,
            'is_reached' => false,
        ]));

        return response()->json($milestone, 201);
    }

    /**
     * Get a milestone.
     *
     * @urlParam project int required The project ID. Example: 1
     * @urlParam milestone int required The milestone ID. Example: 1
     */
    public function show(Project $project, Milestone $milestone): JsonResponse
    {
        if ($milestone->project_id !== $project->id) {
            abort(404);
        }

        return response()->json($milestone);
    }

    /**
     * Update a milestone.
     *
     * @urlParam project int required The project ID. Example: 1
     * @urlParam milestone int required The milestone ID. Example: 1
     */
    public function update(Request $request, Project $project, Milestone $milestone): JsonResponse
    {
        if ($milestone->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'is_reached' => ['nullable', 'boolean'],
            'reached_at' => ['nullable', 'date'],
        ]);

        $milestone->update($validated);

        return response()->json($milestone->fresh());
    }

    /**
     * Delete a milestone.
     *
     * @urlParam project int required The project ID. Example: 1
     * @urlParam milestone int required The milestone ID. Example: 1
     */
    public function destroy(Project $project, Milestone $milestone): JsonResponse
    {
        if ($milestone->project_id !== $project->id) {
            abort(404);
        }

        $milestone->delete();

        return response()->json(null, 204);
    }
}
