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
        // Chantier 19 (Lot 2): the real TimeEntries/Form.vue create form
        // never sent employee_id at all (it has no way to know its own
        // caller's hr_employees.id — nothing shares it via Inertia), and
        // this field was `required` — every real entry creation through
        // the UI 422'd. Defaults to the caller's own linked employee,
        // matching the identical pattern already used by
        // TimesheetAdvancedController::storeSheet().
        $employeeId = $request->employee_id ?? $request->user()?->employee?->id;
        abort_unless($employeeId, 422, 'This user has no linked employee record.');

        $entry = $this->service->createEntry(
            employee_id: $employeeId,
            entry_date: $request->entry_date,
            hours_worked: $request->hours_worked,
            description: $request->description,
            task_id: $request->task_id,
            notes: $request->notes,
            project_id: $request->project_id,
            billable_hours: $request->boolean('billable') ? (float) $request->hours_worked : 0.0,
            hourly_rate: $request->hourly_rate,
            tenant_id: $request->user()?->company_id,
        );

        return (new TimesheetEntryResource($entry))->response()->setStatusCode(201);
    }

    public function show(TimesheetEntry $entry): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        // Chantier 32.19 (Timesheets deep 14-layer audit): TimesheetEntryPolicy::view()
        // was fully written (own entry OR admin/manager/hr-manager) but this
        // endpoint never called it — any authenticated "employee" could read
        // any other employee's individual entry (description/notes/hours)
        // by id, a real IDOR confirmed empirically over a real HTTP request.
        $this->authorize('view', $entry);

        $entry->load(['employee', 'project', 'task', 'submitter', 'approver']);

        return new TimesheetEntryResource($entry);
    }

    public function update(UpdateTimesheetEntryRequest $request, TimesheetEntry $entry): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $this->authorize('update', $entry);

        // Chantier 32.19 (Timesheets deep 14-layer audit, layer 8 —
        // business validation): TimesheetEntryPolicy::update() deliberately
        // lets an admin/manager attempt to edit a submitted/approved entry
        // (and an owner attempt to edit their own rejected entry), but
        // TimesheetService::updateEntry()'s own status guard
        // (canEdit() === status === 'draft') throws a bare \Exception the
        // moment that happens — uncaught, this rendered a raw 500 instead
        // of a clean rejection, confirmed empirically via tinker before
        // this fix. Caught here and translated into the same 422 shape
        // TimeAllocationController::store()/allocate() already use for
        // their own InvalidArgumentException.
        try {
            $updated = $this->service->updateEntry(
                entry: $entry,
                hours_worked: $request->hours_worked,
                description: $request->description,
                task_id: $request->task_id,
                notes: $request->notes,
                project_id: $request->project_id,
                billable_hours: $request->has('billable') ? ($request->boolean('billable') ? (float) ($request->hours_worked ?? $entry->hours_worked) : 0.0) : null,
                hourly_rate: $request->hourly_rate,
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

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

        try {
            $submitted = $this->service->submitEntry($entry);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

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
        // Chantier 32.19 (Timesheets deep 14-layer audit): zero authorization
        // of any kind — any authenticated "employee" could pass an arbitrary
        // ?employee_id= and read that other employee's full entry list
        // (dates/hours/descriptions/notes), confirmed empirically. Same
        // own-employee-unless-privileged-role gate index() already applies.
        $employeeId = (int) $request->employee_id;
        abort_unless($employeeId, 422, 'employee_id is required.');

        if ($employeeId !== (auth()->user()->employee?->id ?? 0)) {
            abort_unless(auth()->user()->hasAnyRole(['admin', 'manager', 'hr-manager']), 403);
        }

        $entries = $this->service->getEmployeeTimesheets(
            employee_id: $employeeId,
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
