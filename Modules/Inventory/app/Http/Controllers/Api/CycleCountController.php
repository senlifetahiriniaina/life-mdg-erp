<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\CycleCountLine;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\CycleCountService;

/**
 * @group Inventory - Cycle Counts
 */
class CycleCountController extends Controller
{
    public function __construct(private readonly CycleCountService $service) {}

    /**
     * List cycle counts.
     */
    public function index(Request $request): JsonResponse
    {
        $counts = CycleCount::with(['warehouse:id,name', 'assignee:id,name'])
            ->withCount('lines')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('warehouse_id'), fn ($q, $v) => $q->where('warehouse_id', $v))
            ->latest()
            ->paginate(20);

        return response()->json($counts);
    }

    /**
     * Create a cycle count.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:inventory_products,id',
            'count_date' => 'nullable|date',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $cc = $this->service->generateCycleCount($warehouse, $data['product_ids']);

        if (isset($data['assigned_to'])) {
            $cc->update(['assigned_to' => $data['assigned_to']]);
        }

        if (isset($data['count_date'])) {
            $cc->update(['count_date' => $data['count_date']]);
        }

        if (isset($data['notes'])) {
            $cc->update(['notes' => $data['notes']]);
        }

        return response()->json($cc->load('lines.product:id,name,sku'), 201);
    }

    /**
     * Show a cycle count.
     */
    public function show(CycleCount $cycleCount): JsonResponse
    {
        return response()->json(
            $cycleCount->load([
                'warehouse:id,name',
                'assignee:id,name',
                'lines.product:id,name,sku',
                'lines.location:id,name,code',
            ])
        );
    }

    /**
     * Update a cycle count.
     */
    public function update(Request $request, CycleCount $cycleCount): JsonResponse
    {
        abort_if($cycleCount->status === 'completed', 422, 'Cannot edit a completed cycle count.');

        $data = $request->validate([
            'assigned_to' => 'nullable|integer|exists:users,id',
            'count_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:planned,in_progress,cancelled',
        ]);

        $cycleCount->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json($cycleCount->fresh());
    }

    /**
     * Delete a cycle count.
     */
    public function destroy(CycleCount $cycleCount): JsonResponse
    {
        abort_if($cycleCount->status === 'completed', 422, 'Cannot delete a completed cycle count.');

        $cycleCount->delete();

        return response()->json(null, 204);
    }

    /**
     * Record a count for a line.
     */
    public function countLine(Request $request, CycleCount $cycleCount, CycleCountLine $cycleCountLine): JsonResponse
    {
        abort_if($cycleCountLine->cycle_count_id !== $cycleCount->id, 404);
        abort_if($cycleCount->status === 'completed', 422, 'Cycle count is already completed.');

        $data = $request->validate([
            'counted_qty' => 'required|numeric|min:0',
        ]);

        $this->service->recordCount($cycleCountLine, (float) $data['counted_qty']);

        return response()->json($cycleCountLine->fresh());
    }

    /**
     * Validate a cycle count and apply adjustments.
     */
    public function approve(CycleCount $cycleCount): JsonResponse
    {
        abort_if($cycleCount->status === 'completed', 422, 'Already completed.');
        abort_if($cycleCount->status === 'cancelled', 422, 'Cycle count is cancelled.');

        $this->service->validateCount($cycleCount);

        return response()->json($cycleCount->fresh(['lines']));
    }

    /**
     * POST cycle-counts/{cycleCount}/validate — alias of approve(), the verb
     * the routes file registers. Shadows ValidatesRequests::validate() on
     * purpose; controller actions here never used the trait helper.
     */
    public function validate(CycleCount $cycleCount): JsonResponse
    {
        return $this->approve($cycleCount);
    }
}
