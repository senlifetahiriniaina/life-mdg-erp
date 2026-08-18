<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTimeLog;
use Modules\Projects\Models\Task;

/**
 * @group Projects - Time Entries
 *
 * Log and manage time entries against project tasks.
 *
 * Chantier 8.4: was built against Modules\Projects\Models\TimeLog, whose
 * $fillable (hours/date/is_billable) never matched any real migrated
 * table — the real `prj_time_logs` table (also written by
 * ProjectTeamController::storeTimeLog()) has started_at/ended_at/
 * duration_minutes/billable/hourly_rate instead. Rewritten onto
 * Modules\Projects\Models\ProjectTimeLog (the model that already matches
 * the real schema) with a thin manual-entry adapter that converts the
 * simple hours+date API this controller exposes into that timer-shaped
 * storage — ProjectTeamController only supports create+list+stop for time
 * logs, not show/update/destroy of an individual entry, so this
 * task-scoped CRUD is real, additive functionality, not a duplicate.
 */
class TimeEntryController extends Controller
{
    /**
     * List time entries for a task.
     *
     * @urlParam task int required The task ID. Example: 1
     */
    public function index(Request $request, Task $task): JsonResponse
    {
        $entries = ProjectTimeLog::with('user:id,name,email')
            ->where('task_id', $task->id)
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->latest('started_at')
            ->paginate(50);

        return response()->json($entries);
    }

    /**
     * Create a time entry.
     *
     * @urlParam project int required The project ID. Example: 1
     * @urlParam task int required The task ID. Example: 1
     *
     * @bodyParam hours numeric required Hours spent. Example: 2.5
     * @bodyParam date date required Date of work (Y-m-d). Example: 2025-05-01
     * @bodyParam description string Description of work done. Example: Implemented login page
     * @bodyParam is_billable boolean Whether the time is billable. Example: true
     * @bodyParam hourly_rate numeric Hourly rate (overrides task default). Example: 75.00
     */
    public function store(Request $request, Project $project, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $entry = new ProjectTimeLog([
            'project_id' => $project->id,
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
        ]);
        $this->applyManualEntry($entry, $validated);
        $entry->save();

        return response()->json($entry->load('user:id,name,email'), 201);
    }

    /**
     * Get a time entry.
     *
     * @urlParam task int required The task ID. Example: 1
     * @urlParam timeEntry int required The time entry ID. Example: 1
     */
    public function show(Task $task, ProjectTimeLog $timeEntry): JsonResponse
    {
        if ($timeEntry->task_id !== $task->id) {
            abort(404);
        }

        return response()->json($timeEntry->load('user:id,name,email', 'task:id,title'));
    }

    /**
     * Update a time entry.
     *
     * @urlParam task int required The task ID. Example: 1
     * @urlParam timeEntry int required The time entry ID. Example: 1
     */
    public function update(Request $request, Task $task, ProjectTimeLog $timeEntry): JsonResponse
    {
        if ($timeEntry->task_id !== $task->id) {
            abort(404);
        }

        if ($timeEntry->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'You can only edit your own time entries.');
        }

        $validated = $request->validate([
            'hours' => ['sometimes', 'numeric', 'min:0.01', 'max:24'],
            'date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->applyManualEntry($timeEntry, $validated);
        $timeEntry->save();

        return response()->json($timeEntry->fresh()->load('user:id,name,email'));
    }

    /**
     * Delete a time entry.
     *
     * @urlParam task int required The task ID. Example: 1
     * @urlParam timeEntry int required The time entry ID. Example: 1
     */
    public function destroy(Request $request, Task $task, ProjectTimeLog $timeEntry): JsonResponse
    {
        if ($timeEntry->task_id !== $task->id) {
            abort(404);
        }

        if ($timeEntry->user_id !== $request->user()->id && ! $request->user()->hasAnyRole(['super-admin', 'admin'])) {
            abort(403, 'You can only delete your own time entries.');
        }

        $timeEntry->delete();

        return response()->json(null, 204);
    }

    /**
     * Convert the manual hours+date entry shape into ProjectTimeLog's real
     * started_at/ended_at/duration_minutes storage. Missing hours/date on a
     * partial update fall back to the entry's current values.
     */
    private function applyManualEntry(ProjectTimeLog $entry, array $validated): void
    {
        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'])->startOfDay()
            : ($entry->started_at?->copy()->startOfDay() ?? now()->startOfDay());

        $minutes = isset($validated['hours'])
            ? (int) round($validated['hours'] * 60)
            : ($entry->duration_minutes ?? 0);

        $entry->started_at = $date;
        $entry->duration_minutes = $minutes;
        $entry->ended_at = $date->copy()->addMinutes($minutes);

        if (array_key_exists('description', $validated)) {
            $entry->description = $validated['description'];
        }
        if (array_key_exists('is_billable', $validated)) {
            $entry->billable = $validated['is_billable'];
        }
        if (array_key_exists('hourly_rate', $validated)) {
            $entry->hourly_rate = $validated['hourly_rate'];
        }
    }
}
