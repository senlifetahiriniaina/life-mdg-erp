<?php

namespace Modules\Timesheets\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Http\Resources\TimeEntryResource;
use Modules\Timesheets\Models\TimeEntry;

/**
 * @group Controllers - Time Entry
 *
 * Record and manage time entries.
 */
class TimeEntryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $employee = $user->employee;

        $query = TimeEntry::query()
            ->where('tenant_id', $user->tenant_id);

        if (! $user->hasRole('manager|hr-manager')) {
            $query->where('employee_id', $employee->id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('work_date_from')) {
            $query->where('work_date', '>=', $request->work_date_from);
        }

        if ($request->filled('work_date_to')) {
            $query->where('work_date', '<=', $request->work_date_to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return TimeEntryResource::collection(
            $query->orderBy('work_date', 'desc')->paginate($request->per_page ?? 15)
        );
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $employee = $request->filled('employee_id') && $user->hasRole('manager|hr-manager')
            ? Employee::findOrFail($request->employee_id)
            : $user->employee;

        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'task_description' => 'required|string|max:255',
            'work_date' => 'required|date|before_or_equal:today',
            'hours' => 'required|numeric|min:0.25|max:12',
            'billable' => 'boolean',
            'rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['tenant_id'] = $user->tenant_id;
        $validated['employee_id'] = $employee->id;
        $validated['status'] = 'draft';

        TimeEntry::create($validated);

        return response()->json(['message' => 'Time entry created'], 201);
    }

    public function show(TimeEntry $entry)
    {
        $this->authorize('view', $entry);

        return new TimeEntryResource($entry);
    }

    public function update(Request $request, TimeEntry $entry)
    {
        $this->authorize('update', $entry);

        if ($entry->status !== 'draft') {
            abort(403, 'Can only edit draft time entries');
        }

        $validated = $request->validate([
            'task_description' => 'string|max:255',
            'hours' => 'numeric|min:0.25|max:12',
            'billable' => 'boolean',
            'rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $entry->update($validated);

        return new TimeEntryResource($entry);
    }

    public function submit(TimeEntry $entry)
    {
        $this->authorize('update', $entry);
        $entry->update(['status' => 'submitted']);

        return new TimeEntryResource($entry);
    }

    public function destroy(TimeEntry $entry)
    {
        $this->authorize('delete', $entry);

        if ($entry->status !== 'draft') {
            abort(403, 'Can only delete draft time entries');
        }

        $entry->delete();

        return response()->noContent();
    }
}
