<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Models\Task;
use Modules\Projects\Models\TimeLog;

/**
 * @group Projects - Time Entries
 *
 * Log and manage time entries against project tasks.
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
        $entries = TimeLog::with('user:id,name,email')
            ->where('task_id', $task->id)
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->latest('date')
            ->paginate(50);

        return response()->json($entries);
    }

    /**
     * Create a time entry.
     *
     * @urlParam task int required The task ID. Example: 1
     *
     * @bodyParam hours numeric required Hours spent. Example: 2.5
     * @bodyParam date date required Date of work (Y-m-d). Example: 2025-05-01
     * @bodyParam description string Description of work done. Example: Implemented login page
     * @bodyParam is_billable boolean Whether the time is billable. Example: true
     * @bodyParam hourly_rate numeric Hourly rate (overrides task default). Example: 75.00
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $entry = TimeLog::create(array_merge($validated, [
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
        ]));

        return response()->json($entry->load('user:id,name,email'), 201);
    }

    /**
     * Get a time entry.
     *
     * @urlParam task int required The task ID. Example: 1
     * @urlParam timeEntry int required The time entry ID. Example: 1
     */
    public function show(Task $task, TimeLog $timeEntry): JsonResponse
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
    public function update(Request $request, Task $task, TimeLog $timeEntry): JsonResponse
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

        $timeEntry->update($validated);

        return response()->json($timeEntry->fresh()->load('user:id,name,email'));
    }

    /**
     * Delete a time entry.
     *
     * @urlParam task int required The task ID. Example: 1
     * @urlParam timeEntry int required The time entry ID. Example: 1
     */
    public function destroy(Request $request, Task $task, TimeLog $timeEntry): JsonResponse
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
}
