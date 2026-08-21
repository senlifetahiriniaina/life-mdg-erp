<?php

namespace Modules\Timesheets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Timesheets\Http\Requests\StoreTimeAllocationRequest;
use Modules\Timesheets\Http\Requests\UpdateTimeAllocationRequest;
use Modules\Timesheets\Http\Resources\TimeAllocationResource;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Services\TimesheetService;

/**
 * @group Controllers - Time Allocation
 *
 * Manage Time Allocation resources.
 */
class TimeAllocationController extends Controller
{
    public function __construct(
        private TimesheetService $service
    ) {}

    public function index(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        // Chantier 32.19 (Timesheets deep 14-layer audit): TimeAllocation had
        // no Policy class and zero authorize() calls anywhere in this
        // controller — any authenticated "employee" could list/view/update/
        // delete ANY other employee's hour allocations (project/cost-center/
        // task split, hourly rate, cost amount), confirmed empirically.
        // Reuses the already-registered TimesheetEntryPolicy (own entry OR
        // admin/manager/hr-manager) via the allocation's linked entry,
        // rather than inventing a second, parallel policy for the same
        // ownership concept.
        $employeeId = auth()->user()->employee?->id ?? 0;

        $allocations = TimeAllocation::query()
            ->with(['entry', 'project', 'task'])
            ->when(
                ! auth()->user()->hasAnyRole(['admin', 'manager', 'hr-manager']),
                fn ($q) => $q->whereHas('entry', fn ($eq) => $eq->where('employee_id', $employeeId))
            )
            ->when($request->entry_id, fn ($q) => $q->where('entry_id', $request->entry_id))
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->when($request->has('billable'), fn ($q) => $q->where('billable', $request->billable == '1' ? 'yes' : 'no'))
            ->paginate($request->per_page ?? 50);

        return TimeAllocationResource::collection($allocations);
    }

    public function store(StoreTimeAllocationRequest $request): JsonResponse
    {
        $entry = TimesheetEntry::findOrFail($request->entry_id);
        $this->authorize('update', $entry);

        try {
            $allocation = $this->service->allocateTime(
                entry_id: $request->entry_id,
                allocations: $request->allocations
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['allocations' => [$e->getMessage()]]], 422);
        }

        return response()->json(TimeAllocationResource::collection($allocation), 201);
    }

    public function show(TimeAllocation $allocation): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $allocation->load(['entry', 'project', 'task']);
        $this->authorize('view', $allocation->entry);

        return new TimeAllocationResource($allocation);
    }

    public function update(UpdateTimeAllocationRequest $request, TimeAllocation $allocation): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $this->authorize('update', $allocation->entry);

        $allocation->update($request->validated());
        $allocation->cost_amount = $allocation->calculateCost();
        $allocation->save();

        return new TimeAllocationResource($allocation);
    }

    public function destroy(TimeAllocation $allocation): JsonResponse
    {
        $this->authorize('update', $allocation->entry);

        $allocation->forceDelete();

        return response()->json(['message' => 'Time allocation deleted'], 200);
    }

    public function allocate(Request $request, TimesheetEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry);

        // Chantier 32.19: this endpoint had zero request validation at all
        // (unlike the sibling POST /allocations, which goes through
        // StoreTimeAllocationRequest) — an omitted `allocations` key threw
        // a raw TypeError instead of a 422, and project_id/task_id/hours
        // were never checked against real data, confirmed empirically.
        // Kept as a real, working alternate API entry point per API First
        // (no frontend caller today), matching the same rationale already
        // documented for TimesheetAdvancedController::submitPeriod().
        $validated = $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.project_id' => 'required|exists:time_tracking_projects,id',
            'allocations.*.cost_center_id' => 'nullable|integer',
            'allocations.*.task_id' => 'nullable|exists:prj_tasks,id',
            'allocations.*.hours' => 'required|numeric|min:0.25',
            'allocations.*.hourly_rate' => 'required|numeric|min:0',
            'allocations.*.is_billable' => 'boolean',
        ]);

        try {
            $allocations = $this->service->allocateTime(
                entry_id: $entry->id,
                allocations: $validated['allocations']
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['allocations' => [$e->getMessage()]]], 422);
        }

        return response()->json(TimeAllocationResource::collection($allocations), 201);
    }

    public function byProject(Request $request, int $project): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $allocations = TimeAllocation::query()
            ->with(['entry', 'project'])
            ->where('project_id', $project)
            ->when($request->from_date, fn ($q) => $q->whereHas('entry', fn ($sq) => $sq->whereDate('entry_date', '>=', $request->from_date))
            )
            ->when($request->to_date, fn ($q) => $q->whereHas('entry', fn ($sq) => $sq->whereDate('entry_date', '<=', $request->to_date))
            )
            ->get();

        return TimeAllocationResource::collection($allocations);
    }
}
