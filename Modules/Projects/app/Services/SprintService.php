<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Projects\Models\Sprint;
use Modules\Projects\Models\Task;

class SprintService
{
    /**
     * Start a sprint (set status → active, record start_date if null).
     */
    public function startSprint(Sprint $sprint): void
    {
        $sprint->update([
            'status' => 'active',
            'start_date' => $sprint->start_date ?? now()->toDateString(),
        ]);
    }

    /**
     * Complete a sprint.
     * Returns un-finished tasks (not done/cancelled).
     *
     * @return array<string,mixed>
     */
    public function completeSprint(Sprint $sprint): array
    {
        $sprint->update(['status' => 'completed']);

        $incomplete = Task::where('sprint_id', $sprint->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get(['id', 'title', 'status', 'story_points', 'assignee_id']);

        return [
            'sprint' => $sprint->fresh(),
            'incomplete_tasks' => $incomplete,
        ];
    }

    /**
     * Compute velocity (sum of completed story_points) for the last N completed sprints.
     *
     * @return array<string,mixed>
     */
    public function getVelocity(int $projectId, int $last = 5): array
    {
        $sprints = Sprint::where('project_id', $projectId)
            ->where('status', 'completed')
            ->orderByDesc('end_date')
            ->limit($last)
            ->with(['tasks' => fn ($q) => $q->where('status', 'done')->select('id', 'sprint_id', 'story_points')])
            ->get();

        $velocities = $sprints->map(fn (Sprint $s): array => [
            'sprint_id' => $s->id,
            'sprint_name' => $s->name,
            'end_date' => $s->end_date?->toDateString(),
            'points' => (int) $s->tasks->sum('story_points'),
        ])->values()->all();

        $avg = count($velocities) > 0
            ? round(array_sum(array_column($velocities, 'points')) / count($velocities), 1)
            : 0.0;

        return [
            'project_id' => $projectId,
            'sprints_analyzed' => count($velocities),
            'average_velocity' => $avg,
            'velocities' => $velocities,
        ];
    }

    /**
     * Burndown: remaining story_points per calendar day of a sprint.
     *
     * @return array<string,mixed>
     */
    public function getBurndown(Sprint $sprint): array
    {
        if (! $sprint->start_date || ! $sprint->end_date) {
            return ['error' => 'Sprint has no start or end date'];
        }

        $tasks = Task::where('sprint_id', $sprint->id)
            ->select('id', 'sprint_id', 'status', 'story_points', 'completed_at')
            ->get();

        $totalPoints = (int) $tasks->sum('story_points');
        $start = $sprint->start_date->copy();
        $end = $sprint->end_date->copy();

        // Build ideal burndown
        $days = max(1, $start->diffInDays($end));
        $ideal = [];
        $actual = [];

        $current = $start->copy();
        $day = 0;

        while ($current->lte($end)) {
            $dateStr = $current->toDateString();

            // Ideal: linear burn
            $idealRemaining = round($totalPoints * (1 - $day / $days));

            // Actual: points still open at end of this day
            $burnedByDay = (int) $tasks
                ->filter(fn (Task $t): bool => $t->completed_at !== null && $t->completed_at->lte($current->endOfDay()))
                ->sum('story_points');

            $ideal[] = ['date' => $dateStr, 'points' => (int) $idealRemaining];
            $actual[] = ['date' => $dateStr, 'points' => $totalPoints - $burnedByDay];

            $current->addDay();
            $day++;
        }

        return [
            'sprint_id' => $sprint->id,
            'sprint_name' => $sprint->name,
            'total_points' => $totalPoints,
            'ideal' => $ideal,
            'actual' => $actual,
        ];
    }

    /**
     * Backlog: tasks not assigned to any sprint for this project.
     *
     * @return Collection<int, Task>
     */
    public function getBacklog(int $projectId): Collection
    {
        return Task::where('project_id', $projectId)
            ->whereNull('sprint_id')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->orderBy('sequence')
            ->get();
    }
}
