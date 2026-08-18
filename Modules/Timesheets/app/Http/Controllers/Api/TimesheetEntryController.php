<?php

namespace Modules\Timesheets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Timesheets\Http\Requests\StoreTimesheetEntryRequest;
use Modules\Timesheets\Http\Requests\UpdateTimesheetEntryRequest;
use Modules\Timesheets\Http\Resources\TimesheetEntryResource;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Services\TimesheetService;

/**
 * @group Controllers - Timesheet Entry
 *
 * Manage Timesheet Entry resources.
 */
class TimesheetEntryController extends Controller
{
    public function __construct(
        private TimesheetService $service
    ) {}

    public function index(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $this->authorize('viewAny', TimesheetEntry::class);

        $query = TimesheetEntry::query()
            ->with(['employee', 'project', 'task', 'submitter', 'approver']);

        // Non-managers can only see their own timesheets. Chantier 8.4: was
        // auth()->id() — a users.id, not the hr_employees.id this column
        // actually stores (same ID-space bug fixed in TimesheetEntryPolicy).
        if (! auth()->user()->hasAnyRole(['admin', 'manager', 'hr-manager'])) {
            $query->where('employee_id', auth()->user()->employee?->id ?? 0);
        } elseif ($request->employee_id) {
            // Managers can filter by employee
            $query->where('employee_id', $request->employee_id);
        }

        $entries = $query
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from_date, fn ($q) => $q->whereDate('entry_date', '>=', $request->from_date))
            ->when($request->to_date, fn ($q) => $q->whereDate('entry_date', '<=', $request->to_date))
            ->latest('entry_date')
            ->paginate($request->per_page ?? 50);

        return TimesheetEntryResource::collection($entries);
    }

    public function store(StoreTimesheetEntryRequest $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $entry = $this->service->createEntry(
            employee_id: $request->employee_id,
            entry_date: $request->entry_date,
            hours_worked: $request->hours_worked,
            description: $request->description,
            task_id: $request->task_id,
            notes: $request->notes
        );

        return (new TimesheetEntryResource($entry))->response()->setStatusCode(201);
    }

    public function show(TimesheetEntry $entry): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $entry->load(['employee', 'project', 'task', 'submitter', 'approver']);

        return new TimesheetEntryResource($entry);
    }

    public function update(UpdateTimesheetEntryRequest $request, TimesheetEntry $entry): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $this->authorize('update', $entry);

        $updated = $this->service->updateEntry(
            entry: $entry,
            hours_worked: $request->hours_worked,
            description: $request->description,
            task_id: $request->task_id,
            notes: $request->notes
        );

        return new TimesheetEntryResource($updated);
    }

    public function destroy(TimesheetEntry $entry): JsonResponse
    {
        $this->authorize('delete', $entry);

        $entry->forceDelete();

        return response()->json(['message' => 'Timesheet entry deleted'], 200);
    }

    public function submit(TimesheetEntry $entry): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $this->authorize('update', $entry);

        $submitted = $this->service->submitEntry($entry);

        return new TimesheetEntryResource($submitted);
    }

    public function approve(Request $request, TimesheetEntry $entry): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $this->authorize('approve', $entry);

        $approved = $this->service->approveEntry(
            entry: $entry,
            approved_by: auth()->id(),
            notes: $request->notes
        );

        return new TimesheetEntryResource($approved);
    }

    public function reject(Request $request, TimesheetEntry $entry): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $this->authorize('approve', $entry);

        $rejected = $this->service->rejectEntry(
            entry: $entry,
            rejected_by: auth()->id(),
            notes: $request->notes
        );

        return new TimesheetEntryResource($rejected);
    }

    public function byEmployee(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $entries = $this->service->getEmployeeTimesheets(
            employee_id: $request->employee_id,
            from_date: $request->from_date,
            to_date: $request->to_date
        );

        return TimesheetEntryResource::collection($entries);
    }

    public function pendingApprovals(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $entries = $this->service->getPendingApprovals(
            department_id: $request->department_id,
            limit: $request->limit ?? 50
        );

        return TimesheetEntryResource::collection($entries);
    }
}
