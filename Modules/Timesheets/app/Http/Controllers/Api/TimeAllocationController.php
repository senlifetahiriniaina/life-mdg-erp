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
        $allocations = TimeAllocation::query()
            ->with(['entry', 'project', 'costCenter', 'task'])
            ->when($request->entry_id, fn ($q) => $q->where('entry_id', $request->entry_id))
            ->when($request->project_id, fn ($q) => $q->where('project_id', $request->project_id))
            ->when($request->has('billable'), fn ($q) => $q->where('billable', $request->billable == '1' ? 'yes' : 'no'))
            ->paginate($request->per_page ?? 50);

        return TimeAllocationResource::collection($allocations);
    }

    public function store(StoreTimeAllocationRequest $request): JsonResponse
    {
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
        $allocation->load(['entry', 'project', 'costCenter', 'task']);

        return new TimeAllocationResource($allocation);
    }

    public function update(UpdateTimeAllocationRequest $request, TimeAllocation $allocation): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $allocation->update($request->validated());
        $allocation->cost_amount = $allocation->calculateCost();
        $allocation->save();

        return new TimeAllocationResource($allocation);
    }

    public function destroy(TimeAllocation $allocation): JsonResponse
    {
        $allocation->forceDelete();

        return response()->json(['message' => 'Time allocation deleted'], 200);
    }

    public function allocate(Request $request, TimesheetEntry $entry): JsonResponse
    {
        $allocations = $this->service->allocateTime(
            entry_id: $entry->id,
            allocations: $request->allocations
        );

        return response()->json(TimeAllocationResource::collection($allocations), 201);
    }

    public function byProject(Request $request, int $project): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $allocations = TimeAllocation::query()
            ->with(['entry', 'project', 'costCenter'])
            ->where('project_id', $project)
            ->when($request->from_date, fn ($q) => $q->whereHas('entry', fn ($sq) => $sq->whereDate('entry_date', '>=', $request->from_date))
            )
            ->when($request->to_date, fn ($q) => $q->whereHas('entry', fn ($sq) => $sq->whereDate('entry_date', '<=', $request->to_date))
            )
            ->get();

        return TimeAllocationResource::collection($allocations);
    }
}
