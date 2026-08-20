<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\AutomationRule;
use Modules\Projects\Models\Project;

/**
 * @group Projects - Automation
 *
 * Workflow automation rules.
 */
class AutomationController extends Controller
{
    use ScopesToProjectCompany;

    /**
     * List automation rules for a project.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $rules = AutomationRule::where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $rules]);
    }

    /**
     * Create a new automation rule.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string', 'in:task_created,task_status_changed,task_assigned,comment_added'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['required', 'array', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ]);

        $rule = AutomationRule::create(array_merge($validated, [
            'project_id' => $project->id,
            'conditions' => $validated['conditions'] ?? [],
        ]));

        return response()->json($rule, 201);
    }

    /**
     * Show a single rule.
     */
    public function show(Request $request, Project $project, AutomationRule $automation): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($automation->project_id !== $project->id) {
            abort(404);
        }

        return response()->json($automation);
    }

    /**
     * Update a rule.
     */
    public function update(Request $request, Project $project, AutomationRule $automation): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($automation->project_id !== $project->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'trigger' => ['sometimes', 'string', 'in:task_created,task_status_changed,task_assigned,comment_added'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['sometimes', 'array', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ]);

        $automation->update($validated);

        return response()->json($automation->fresh());
    }

    /**
     * Delete a rule.
     */
    public function destroy(Request $request, Project $project, AutomationRule $automation): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        if ($automation->project_id !== $project->id) {
            abort(404);
        }

        $automation->delete();

        return response()->json(null, 204);
    }
}
