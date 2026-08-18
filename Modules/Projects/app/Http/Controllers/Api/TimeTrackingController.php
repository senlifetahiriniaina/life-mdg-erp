<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\TimeEntry;
use Modules\Projects\Services\TimeTrackingService;

/**
 * @group Projects - Time Tracking & Billing
 *
 * Track time and manage billing for projects.
 */
class TimeTrackingController extends Controller
{
    public function __construct(private readonly TimeTrackingService $service) {}

    /**
     * List time entries (optionally filtered by project_id or user_id).
     */
    public function index(Request $request): JsonResponse
    {
        $query = TimeEntry::with(['project:id,name', 'user:id,name,email'])
            ->when($request->project_id, fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->latest('started_at');

        return response()->json($query->paginate(50));
    }

    /**
     * Log a time entry (completed, with start + end).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:prj_projects,id'],
            'task_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'billable' => ['nullable', 'boolean'],
        ]);

        $entry = $this->service->logTime(
            $validated['project_id'],
            $request->user()->id,
            $validated
        );

        return response()->json($entry->load(['project:id,name', 'user:id,name,email']), 201);
    }

    /**
     * Show a time entry.
     */
    public function show(TimeEntry $timeEntry): JsonResponse
    {
        return response()->json($timeEntry->load(['project:id,name', 'user:id,name,email']));
    }

    /**
     * Update a time entry.
     */
    public function update(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        if ($timeEntry->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'You can only edit your own time entries.');
        }

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'billable' => ['nullable', 'boolean'],
        ]);

        $timeEntry->update($validated);

        return response()->json($timeEntry->fresh()->load(['project:id,name', 'user:id,name,email']));
    }

    /**
     * Delete a time entry.
     */
    public function destroy(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        if ($timeEntry->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'You can only delete your own time entries.');
        }

        $timeEntry->delete();

        return response()->json(null, 204);
    }

    /**
     * Start a timer (creates a running entry with no ended_at).
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:prj_projects,id'],
            'task_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'billable' => ['nullable', 'boolean'],
        ]);

        $entry = $this->service->startTimer(
            $validated['project_id'],
            $request->user()->id,
            $validated
        );

        return response()->json($entry->load(['project:id,name', 'user:id,name,email']), 201);
    }

    /**
     * Stop a running timer.
     */
    public function stop(TimeEntry $timeEntry): JsonResponse
    {
        if (! $timeEntry->isRunning()) {
            return response()->json(['message' => 'Timer is not running.'], 422);
        }

        $entry = $this->service->stopTimer($timeEntry);

        return response()->json($entry->load(['project:id,name', 'user:id,name,email']));
    }

    /**
     * Mark a time entry as billed.
     */
    public function bill(TimeEntry $timeEntry): JsonResponse
    {
        $timeEntry->markBilled();

        return response()->json($timeEntry->fresh()->load(['project:id,name', 'user:id,name,email']));
    }

    /**
     * List all time entries for a specific project.
     */
    public function projectTimeEntries(Request $request, Project $project): JsonResponse
    {
        $entries = TimeEntry::with(['user:id,name,email'])
            ->where('project_id', $project->id)
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->latest('started_at')
            ->paginate(50);

        return response()->json($entries);
    }

    /**
     * Get billing stats for a project.
     */
    public function projectBilling(Project $project): JsonResponse
    {
        $billing = $project->billing ?? null;
        $stats = $this->service->getProjectBillingStats($project->id);

        return response()->json([
            'billing' => $billing,
            'stats' => $stats,
        ]);
    }

    /**
     * Set up or update billing configuration for a project.
     */
    public function setupBilling(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'billing_type' => ['required', 'in:fixed,hourly,milestone'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'budget_hours' => ['nullable', 'numeric', 'min:0'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,paused,completed'],
        ]);

        $billing = $this->service->setupProjectBilling($project->id, $validated);

        return response()->json($billing, 201);
    }

    /**
     * Mark all billable unbilled entries for a project as billed.
     */
    public function markBilled(Project $project): JsonResponse
    {
        $count = $this->service->markEntriesAsBilled($project->id);

        return response()->json(['marked_billed' => $count]);
    }

    /**
     * Cross-project time report, grouped by member/project/task.
     *
     * Chantier 8.4: resources/js/Pages/Projects/TimeReport/Index.vue (the
     * real, routed root-level page) calls GET projects/time-report-global
     * with no backing endpoint anywhere — every request silently fell back
     * to its client-side empty-report catch. Built here rather than on
     * ProjectTeamController::timeReport() (single-project) since this is
     * the one cross-project aggregate, and this controller already owns
     * the global (non-project-scoped) time-entries endpoints against the
     * real TimeEntry model this report needs.
     */
    public function globalReport(Request $request): JsonResponse
    {
        $groupBy = in_array($request->input('group_by'), ['member', 'project', 'task'], true)
            ? $request->input('group_by')
            : 'member';

        $entries = TimeEntry::with(['project:id,name', 'user:id,name', 'task:id,title'])
            ->whereNotNull('ended_at')
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('started_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('started_at', '<=', $request->date('date_to')))
            ->get();

        $groups = $entries->groupBy(function ($entry) use ($groupBy) {
            return match ($groupBy) {
                'project' => $entry->project_id,
                'task' => $entry->task_id ?? 'none',
                default => $entry->user_id,
            };
        });

        $rows = $groups->map(function ($group, $key) use ($groupBy) {
            $first = $group->first();
            $hours = $group->sum('duration_minutes') / 60;
            $billableHours = $group->where('billable', true)->sum('duration_minutes') / 60;
            $amount = $group->sum(fn (TimeEntry $e) => $e->billableAmount());

            return [
                'key' => (string) $key,
                'label' => match ($groupBy) {
                    'project' => $first->project?->name ?? 'Sans projet',
                    'task' => $first->task?->title ?? 'Sans tâche',
                    default => $first->user?->name ?? 'Inconnu',
                },
                'member' => $first->user?->name,
                'project' => $first->project?->name,
                'task' => $first->task?->title,
                'hours' => round($hours, 2),
                'billable_hours' => round($billableHours, 2),
                'rate' => $billableHours > 0 ? round($amount / $billableHours, 2) : 0,
                'amount' => round($amount, 2),
            ];
        })->values();

        return response()->json([
            'total_hours' => round($entries->sum('duration_minutes') / 60, 2),
            'billable_hours' => round($entries->where('billable', true)->sum('duration_minutes') / 60, 2),
            'billable_amount' => round($entries->sum(fn (TimeEntry $e) => $e->billableAmount()), 2),
            'rows' => $rows,
        ]);
    }
}
