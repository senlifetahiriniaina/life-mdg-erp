<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTimeLog;

class ProjectReportService
{
    /**
     * @return array<string, mixed>
     */
    public function generateStatusReport(Project $project): array
    {
        $project->loadMissing(['tasks', 'milestones', 'teamMembers.user', 'owner']);

        $tasks = $project->tasks ?? collect();

        $tasksByStatus = $tasks->groupBy('status')->map->count()->toArray();
        $totalTasks = $tasks->count();
        $doneTasks = $tasksByStatus['done'] ?? 0;
        $progress = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0;

        // Team hours
        $logs = ProjectTimeLog::with('user')
            ->where('project_id', $project->id)
            ->whereNotNull('ended_at')
            ->get();

        $teamHours = $logs->groupBy('user_id')->map(function ($userLogs, $userId) {
            $user = $userLogs->first()?->user;
            $minutes = $userLogs->sum('duration_minutes');
            $billable = $userLogs->where('billable', true)->sum('duration_minutes');
            $billableAmount = $userLogs->where('billable', true)
                ->reduce(fn (float $carry, ProjectTimeLog $l) => $carry + (($l->duration_minutes ?? 0) / 60) * ((float) ($l->hourly_rate ?? 0)), 0.0);

            return [
                'user_id' => $userId,
                'name' => $user?->name ?? 'Unknown',
                'total_hours' => round($minutes / 60, 2),
                'billable_hours' => round($billable / 60, 2),
                'hourly_rate' => $userLogs->first()?->hourly_rate,
                'billable_amount' => $billableAmount,
            ];
        })->values()->toArray();

        $totalMinutes = (int) $logs->sum('duration_minutes');
        $budgetSpent = (float) collect($teamHours)->sum('billable_amount');

        // Milestones
        $milestones = $project->milestones->map(fn ($m) => [
            'name' => $m->name,
            'due_date' => $m->due_date?->toDateString(),
            'reached' => $m->is_reached,
        ])->toArray();

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'status' => $project->status,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                'budget' => $project->budget,
                'currency' => $project->currency,
                'is_billable' => $project->is_billable,
                'owner' => $project->owner?->name,
            ],
            'progress' => $progress,
            'task_summary' => [
                'total' => $totalTasks,
                'by_status' => $tasksByStatus,
                'done' => $doneTasks,
            ],
            'team_hours' => $teamHours,
            'total_hours' => round($totalMinutes / 60, 2),
            'milestones' => $milestones,
            'budget' => [
                'estimated' => $project->budget,
                'spent' => round($budgetSpent, 2),
                'remaining' => $project->budget ? round((float) $project->budget - $budgetSpent, 2) : null,
            ],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateGanttData(Project $project): array
    {
        $project->loadMissing(['tasks', 'milestones']);

        $tasks = $project->tasks->map(fn ($task) => [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'start_date' => $task->start_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'progress' => $task->status === 'done' ? 100 : ($task->status === 'in_progress' ? 50 : 0),
        ])->toArray();

        $milestones = $project->milestones->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'due_date' => $m->due_date?->toDateString(),
            'reached' => $m->is_reached,
        ])->toArray();

        return [
            'project_id' => $project->id,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'tasks' => $tasks,
            'milestones' => $milestones,
        ];
    }

    public function renderReportHtml(Project $project): string
    {
        $data = $this->generateStatusReport($project);

        return view('projects.report', compact('data', 'project'))->render();
    }

    public function exportPdf(Project $project): string
    {
        $html = $this->renderReportHtml($project);

        $pdf = Pdf::loadHTML($html);

        $filename = 'project-report-'.$project->id.'-'.now()->format('Y-m-d').'.pdf';
        $path = 'reports/'.$filename;

        Storage::put($path, $pdf->output());

        return $path;
    }
}
