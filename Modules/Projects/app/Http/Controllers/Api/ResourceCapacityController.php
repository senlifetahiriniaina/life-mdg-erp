<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\ResourceAllocation;
use Modules\Projects\Services\ResourceCapacityService;

/**
 * @group Controllers - Resource Capacity
 *
 * Manage Resource Capacity resources.
 */
class ResourceCapacityController extends Controller
{
    use ScopesToProjectCompany;

    public function __construct(private readonly ResourceCapacityService $service) {}

    // -------------------------------------------------------------------------
    // Allocations CRUD
    // -------------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $query = ResourceAllocation::with('user', 'project', 'task')
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->project_id, fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v));

        return response()->json($query->latest()->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:prj_projects,id',
            'task_id' => 'nullable|integer|exists:prj_tasks,id',
            'user_id' => 'required|integer|exists:users,id',
            'allocation_type' => 'sometimes|in:full_time,part_time,as_needed',
            'allocation_percent' => 'sometimes|integer|min:1|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'hours_per_day' => 'sometimes|numeric|min:0|max:24',
            'status' => 'sometimes|in:planned,confirmed,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $allocation = $this->service->allocate(
            $validated['user_id'],
            $validated['project_id'],
            $validated,
        );

        return response()->json($allocation->load('user', 'project', 'task'), 201);
    }

    public function show(ResourceAllocation $allocation): JsonResponse
    {
        return response()->json($allocation->load('user', 'project', 'task'));
    }

    public function update(Request $request, ResourceAllocation $allocation): JsonResponse
    {
        $validated = $request->validate([
            'allocation_type' => 'sometimes|in:full_time,part_time,as_needed',
            'allocation_percent' => 'sometimes|integer|min:1|max:100',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'hours_per_day' => 'sometimes|numeric|min:0|max:24',
            'actual_hours_logged' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:planned,confirmed,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $allocation->update($validated);

        return response()->json($allocation->load('user', 'project', 'task'));
    }

    public function destroy(ResourceAllocation $allocation): JsonResponse
    {
        $allocation->delete();

        return response()->json(null, 204);
    }

    // -------------------------------------------------------------------------
    // Capacity analysis
    // -------------------------------------------------------------------------

    public function utilizationReport(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from',
        ]);

        $from = Carbon::parse($request->get('from', now()->startOfMonth()));
        $to = Carbon::parse($request->get('to', now()->endOfMonth()));
        $report = $this->service->utilizationReport($from, $to);

        return response()->json(['data' => $report]);
    }

    public function suggestAllocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|integer|exists:prj_projects,id',
            'requirements' => 'required|array|min:1',
            'requirements.*.hours' => 'sometimes|integer|min:1',
            'requirements.*.start_date' => 'sometimes|date',
            'requirements.*.end_date' => 'sometimes|date',
        ]);

        $suggestions = $this->service->suggestAllocations(
            $validated['project_id'],
            $validated['requirements'],
        );

        return response()->json(['data' => $suggestions]);
    }

    public function getAvailability(Request $request, int $user): JsonResponse
    {
        $request->validate([
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from',
        ]);

        $from = Carbon::parse($request->get('from', now()->toDateString()));
        $to = Carbon::parse($request->get('to', now()->addDays(30)->toDateString()));
        $availability = $this->service->getAvailability($user, $from, $to);

        return response()->json(['data' => $availability]);
    }

    public function setLeave(Request $request, int $user): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'is_holiday' => 'sometimes|boolean',
        ]);

        $records = $this->service->setLeave(
            $user,
            Carbon::parse($validated['from']),
            Carbon::parse($validated['to']),
            (bool) ($validated['is_holiday'] ?? false),
        );

        return response()->json(['data' => $records], 201);
    }

    public function checkOverallocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $result = $this->service->checkOverallocation(
            (int) $validated['user_id'],
            Carbon::parse($validated['from']),
            Carbon::parse($validated['to']),
        );

        return response()->json($result);
    }

    /**
     * Chantier 19 Lot 2: took a bare project id straight into the service
     * with zero company-ownership check — any authenticated employee/
     * manager/admin of ANY company could read another company's resource
     * demand by guessing a project id. Fixed to resolve+assert company
     * ownership first, matching the same fix applied to
     * ProjectAdvancedController's budget/kpis/risks endpoints.
     */
    public function getProjectDemand(Request $request, int $project): JsonResponse
    {
        $this->resolveCompanyScopedProject($request, $project);

        $request->validate([
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from',
        ]);

        $from = Carbon::parse($request->get('from', now()->toDateString()));
        $to = Carbon::parse($request->get('to', now()->addDays(90)->toDateString()));
        $demand = $this->service->getProjectDemand($project, $from, $to);

        return response()->json(['data' => $demand]);
    }
}
