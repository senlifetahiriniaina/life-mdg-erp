<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;

/**
 * @group Projects - Views
 *
 * Data endpoints for Kanban, Calendar and Gantt views.
 */
class ProjectViewsController extends Controller
{
    use ScopesToProjectCompany;

    /**
     * Kanban view data — tasks grouped by status.
     *
     * Returns columns: todo, in_progress, review, done, cancelled.
     */
    public function kanban(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $statuses = ['todo', 'in_progress', 'review', 'done', 'cancelled'];

        $tasks = Task::with('assignee:id,name,email', 'milestone:id,name')
            ->where('project_id', $project->id)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->orderBy('sequence')
            ->get();

        $columns = collect($statuses)->map(fn ($status) => [
            'status' => $status,
            'label' => $this->statusLabel($status),
            'count' => $tasks->where('status', $status)->count(),
            'tasks' => $tasks->where('status', $status)->values()->map(fn ($t) => $this->taskCard($t)),
        ]);

        return response()->json([
            'project' => ['id' => $project->id, 'name' => $project->name, 'color' => $project->color],
            'columns' => $columns,
        ]);
    }

    /**
     * Calendar view data — tasks and milestones with dates.
     *
     * @queryParam from date Start of visible range. Example: 2026-06-01
     * @queryParam to date End of visible range. Example: 2026-06-30
     */
    public function calendar(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $tasks = Task::with('assignee:id,name')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->whereNotNull('due_date')
            ->get()
            ->map(fn ($t) => [
                'id' => "task-{$t->id}",
                'type' => 'task',
                'title' => $t->title,
                'start' => $t->start_date?->toDateString() ?? $t->due_date->toDateString(),
                'end' => $t->due_date->toDateString(),
                'color' => $this->priorityColor($t->priority),
                'status' => $t->status,
                'assignee' => $t->assignee?->name,
            ]);

        $milestones = $project->milestones()
            ->whereNotNull('due_date')
            ->get()
            ->map(fn ($m) => [
                'id' => "milestone-{$m->id}",
                'type' => 'milestone',
                'title' => $m->name,
                'start' => $m->due_date->toDateString(),
                'end' => $m->due_date->toDateString(),
                'color' => '#f59e0b',
                'is_reached' => $m->is_reached,
            ]);

        return response()->json([
            'project' => ['id' => $project->id, 'name' => $project->name, 'color' => $project->color],
            'events' => array_merge($tasks->all(), $milestones->all()),
        ]);
    }

    /**
     * Gantt view data — tasks with start/end dates and dependencies.
     */
    public function gantt(Request $request, Project $project): JsonResponse
    {
        $this->assertSameCompanyAsProject($request, $project);

        $tasks = Task::with('assignee:id,name', 'milestone:id,name', 'dependencies')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->orderBy('sequence')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'parent_id' => $t->parent_id,
                'milestone_id' => $t->milestone_id,
                'milestone_name' => $t->milestone?->name,
                'title' => $t->title,
                'status' => $t->status,
                'priority' => $t->priority,
                'start' => $t->start_date?->toDateString(),
                'end' => $t->due_date?->toDateString(),
                'estimated_hours' => $t->estimated_hours,
                'logged_hours' => $t->logged_hours,
                'progress' => $t->estimated_hours > 0
                    ? min(100, round($t->logged_hours / $t->estimated_hours * 100))
                    : ($t->status === 'done' ? 100 : 0),
                // Chantier 32.17 (14-layer deep audit): $t->dependencies used
                // to resolve Eloquent's raw `dependencies` JSON column
                // (declared in $casts) rather than the dependencies()
                // relation of the same name — Eloquent always prefers a cast
                // attribute over a same-named relation method. That JSON
                // column is never written by any real code path (TaskController
                // never accepts it, GanttController::addDependency() writes
                // real rows to the separate TaskDependency table instead), so
                // this field was guaranteed empty on every real call. Not
                // user-facing today (Gantt.vue discards this endpoint's
                // `tasks` array and only reads its `milestones`, taking real
                // dependency data from GanttController::show() instead) but a
                // real API-contract bug for any other consumer. Fixed to read
                // the real TaskDependency rows via an explicit eager-loaded
                // relation call (can't reference $t->dependencies directly —
                // still resolves to the dead JSON column, not the relation).
                'dependencies' => $t->getRelation('dependencies')->pluck('depends_on_task_id')->values(),
                'assignee' => $t->assignee?->name,
                'color' => $this->priorityColor($t->priority),
            ]);

        $milestones = $project->milestones()
            ->get()
            ->map(fn ($m) => [
                'id' => "m-{$m->id}",
                'type' => 'milestone',
                'title' => $m->name,
                'end' => $m->due_date?->toDateString(),
                'is_reached' => $m->is_reached,
            ]);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
            ],
            'tasks' => $tasks->values(),
            'milestones' => $milestones->values(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function taskCard(Task $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'priority' => $t->priority,
            'due_date' => $t->due_date?->toDateString(),
            'estimated_hours' => $t->estimated_hours,
            'logged_hours' => $t->logged_hours,
            'tags' => $t->tags ?? [],
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            'milestone' => $t->milestone ? ['id' => $t->milestone->id, 'name' => $t->milestone->name] : null,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'todo' => 'À faire',
            'in_progress' => 'En cours',
            'review' => 'En révision',
            'done' => 'Terminé',
            'cancelled' => 'Annulé',
            default => $status,
        };
    }

    private function priorityColor(string $priority): string
    {
        return match ($priority) {
            'urgent' => '#ef4444',
            'high' => '#f97316',
            'medium' => '#3b82f6',
            'low' => '#6b7280',
            default => '#3b82f6',
        };
    }
}
