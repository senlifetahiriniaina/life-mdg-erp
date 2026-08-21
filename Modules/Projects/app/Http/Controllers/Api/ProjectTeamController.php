<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTeamMember;
use Modules\Projects\Models\ProjectTimeLog;
use Modules\Projects\Services\ProjectTeamService;

/**
 * @group Projects - Team
 */
class ProjectTeamController extends Controller
{
    use ScopesToProjectCompany;

    public function __construct(private readonly ProjectTeamService $teamService) {}

    /**
     * List team members of a project.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $members = ProjectTeamMember::with('user')
            ->where('project_id', $project->id)
            ->whereNull('left_at')
            ->get()
            ->map(fn (ProjectTeamMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role,
                'can_edit_tasks' => $m->can_edit_tasks,
                'can_manage_members' => $m->can_manage_members,
                'joined_at' => $m->joined_at->toISOString(),
            ]);

        return response()->json($members);
    }

    /**
     * Add a member to a project.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:owner,manager,member,viewer'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $member = $this->teamService->addMember($project, $user, $validated['role']);

        $member->load('user');

        return response()->json([
            'id' => $member->id,
            'user_id' => $member->user_id,
            'name' => $member->user?->name,
            'email' => $member->user?->email,
            'role' => $member->role,
            'can_edit_tasks' => $member->can_edit_tasks,
            'can_manage_members' => $member->can_manage_members,
            'joined_at' => $member->joined_at->toISOString(),
        ], 201);
    }

    /**
     * Update a team member's role.
     */
    public function update(Request $request, Project $project, ProjectTeamMember $member): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);
        abort_if($member->project_id !== $project->id, 404);

        $validated = $request->validate([
            'role' => ['required', 'in:owner,manager,member,viewer'],
            'can_edit_tasks' => ['sometimes', 'boolean'],
            'can_manage_members' => ['sometimes', 'boolean'],
        ]);

        $this->teamService->updateMemberRole($member, $validated['role']);

        if (isset($validated['can_edit_tasks'])) {
            $member->update(['can_edit_tasks' => $validated['can_edit_tasks']]);
        }
        if (isset($validated['can_manage_members'])) {
            $member->update(['can_manage_members' => $validated['can_manage_members']]);
        }

        return response()->json($member->fresh());
    }

    /**
     * Remove a team member.
     */
    public function destroy(Request $request, Project $project, ProjectTeamMember $member): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);
        abort_if($member->project_id !== $project->id, 404);

        $user = User::find($member->user_id);
        if ($user) {
            $this->teamService->removeMember($project, $user);
        }

        return response()->json(null, 204);
    }

    /**
     * List time logs for a project.
     */
    public function timeLogs(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $logs = ProjectTimeLog::with(['user', 'task'])
            ->where('project_id', $project->id)
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->task_id, fn ($q, $v) => $q->where('task_id', $v))
            ->latest('started_at')
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * Log time manually or start a timer for a project.
     */
    public function storeTimeLog(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $validated = $request->validate([
            'task_id' => ['nullable', 'exists:prj_tasks,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'description' => ['nullable', 'string'],
            'billable' => ['sometimes', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Chantier 32.17 (14-layer deep audit): the 'exists' rule above only
        // confirms the task id is real ANYWHERE in the app, never that it
        // belongs to $project — a caller could log time under their own
        // project's URL while pointing task_id at an unrelated task,
        // including one belonging to a different company. Same bug class
        // fixed on TimeEntryController::store() in this same audit.
        if (isset($validated['task_id'])) {
            $taskProjectId = \Modules\Projects\Models\Task::where('id', $validated['task_id'])->value('project_id');
            abort_if($taskProjectId !== $project->id, 422, 'task_id must belong to this project.');
        }

        /** @var User $user */
        $user = $request->user();
        $log = $this->teamService->logTime($project, $user, $validated);
        $log->load(['user', 'task']);

        return response()->json($log, 201);
    }

    /**
     * Stop an active timer.
     */
    public function stopTimer(Request $request, ProjectTimeLog $log): JsonResponse
    {
        $this->assertSameCompanyAsTimeLog($request, $log);

        if ($log->ended_at !== null) {
            return response()->json(['message' => 'Timer already stopped.'], 422);
        }

        $log = $this->teamService->stopTimer($log);
        $log->load(['user', 'task']);

        return response()->json($log);
    }

    /**
     * Aggregated time report for a project.
     */
    public function timeReport(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $data = $this->teamService->getProjectHours($project);

        return response()->json($data);
    }
}
