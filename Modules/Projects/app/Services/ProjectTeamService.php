<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use App\Models\User;
use Carbon\Carbon;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTeamMember;
use Modules\Projects\Models\ProjectTimeLog;

class ProjectTeamService
{
    public function addMember(Project $project, User $user, string $role): ProjectTeamMember
    {
        /** @var ProjectTeamMember $member */
        $member = ProjectTeamMember::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $user->id],
            [
                'role' => $role,
                'joined_at' => now(),
                'left_at' => null,
                'can_edit_tasks' => in_array($role, ['owner', 'manager', 'member'], true),
                'can_manage_members' => in_array($role, ['owner', 'manager'], true),
            ]
        );

        return $member;
    }

    public function removeMember(Project $project, User $user): void
    {
        ProjectTeamMember::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->update(['left_at' => now()]);
    }

    public function updateMemberRole(ProjectTeamMember $member, string $role): void
    {
        $member->update([
            'role' => $role,
            'can_edit_tasks' => in_array($role, ['owner', 'manager', 'member'], true),
            'can_manage_members' => in_array($role, ['owner', 'manager'], true),
        ]);
    }

    public function logTime(Project $project, User $user, array $data): ProjectTimeLog
    {
        /** @var ProjectTimeLog $log */
        $log = ProjectTimeLog::create([
            'project_id' => $project->id,
            'task_id' => $data['task_id'] ?? null,
            'user_id' => $user->id,
            'started_at' => $data['started_at'],
            'ended_at' => $data['ended_at'] ?? null,
            'duration_minutes' => isset($data['ended_at'])
                ? (int) round(Carbon::parse($data['started_at'])->diffInMinutes(Carbon::parse($data['ended_at'])))
                : null,
            'description' => $data['description'] ?? null,
            'billable' => $data['billable'] ?? false,
            'hourly_rate' => $data['hourly_rate'] ?? null,
        ]);

        return $log;
    }

    public function stopTimer(ProjectTimeLog $log): ProjectTimeLog
    {
        $endedAt = now();
        $log->update([
            'ended_at' => $endedAt,
            'duration_minutes' => (int) round($log->started_at->diffInMinutes($endedAt)),
        ]);

        return $log->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjectHours(Project $project): array
    {
        $logs = ProjectTimeLog::with(['user', 'task'])
            ->where('project_id', $project->id)
            ->whereNotNull('ended_at')
            ->get();

        /** @var array<int, int> $memberMinutes */
        $memberMinutes = [];
        /** @var array<int, string> $memberNames */
        $memberNames = [];
        /** @var array<int, int> $taskMinutes */
        $taskMinutes = [];
        /** @var array<int, string> $taskTitles */
        $taskTitles = [];
        $totalMinutes = 0;

        foreach ($logs as $log) {
            $minutes = $log->duration_minutes ?? 0;
            $totalMinutes += $minutes;

            $userId = $log->user_id;
            $memberMinutes[$userId] = ($memberMinutes[$userId] ?? 0) + $minutes;
            $memberNames[$userId] = $memberNames[$userId] ?? ($log->user?->name ?? 'Unknown');

            if ($log->task_id !== null) {
                $taskId = $log->task_id;
                $taskMinutes[$taskId] = ($taskMinutes[$taskId] ?? 0) + $minutes;
                $taskTitles[$taskId] = $taskTitles[$taskId] ?? ($log->task?->title ?? 'Unknown');
            }
        }

        $byMember = [];
        foreach ($memberMinutes as $uid => $mins) {
            $byMember[] = [
                'user_id' => $uid,
                'name' => $memberNames[$uid],
                'minutes' => $mins,
                'hours' => round($mins / 60, 2),
            ];
        }

        $byTask = [];
        foreach ($taskMinutes as $tid => $mins) {
            $byTask[] = [
                'task_id' => $tid,
                'title' => $taskTitles[$tid],
                'minutes' => $mins,
                'hours' => round($mins / 60, 2),
            ];
        }

        return [
            'by_member' => $byMember,
            'by_task' => $byTask,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2),
        ];
    }
}
