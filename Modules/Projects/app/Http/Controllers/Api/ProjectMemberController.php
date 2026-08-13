<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Models\Project;

/**
 * @group Projects - Members
 */
class ProjectMemberController extends Controller
{
    /**
     * List members of a project.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $members = DB::table('users')
            ->select('users.id', 'users.name', 'users.email', 'prj_members.role')
            ->join('prj_members', 'prj_members.user_id', '=', 'users.id')
            ->where('prj_members.project_id', $project->id)
            ->paginate($request->integer('per_page', 50));

        return response()->json($members);
    }

    /**
     * Add a member to a project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:owner,admin,member,viewer'],
        ]);

        $project->members()->syncWithoutDetaching([
            $validated['user_id'] => ['role' => $validated['role']],
        ]);

        return response()->json(['message' => 'Member added.'], 201);
    }

    /**
     * Update a member's role.
     */
    public function update(Request $request, Project $project, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:owner,admin,member,viewer'],
        ]);

        $project->members()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return response()->json(['message' => 'Role updated.']);
    }

    /**
     * Remove a member from a project.
     */
    public function destroy(Project $project, User $user): JsonResponse
    {
        $project->members()->detach($user->id);

        return response()->json(null, 204);
    }
}
