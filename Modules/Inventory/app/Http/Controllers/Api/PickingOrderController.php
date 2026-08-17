<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\PickingLine;
use Modules\Inventory\Models\PickingOrder;
use Modules\Inventory\Services\WmsService;

/**
 * @group Inventory - WMS Picking
 */
class PickingOrderController extends Controller
{
    public function __construct(private readonly WmsService $service) {}

    /**
     * List picking orders.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = PickingOrder::with(['warehouse:id,name', 'assignee:id,name'])
            ->withCount('lines')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('warehouse_id'), fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->orderBy('priority')
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    /**
     * Create a picking order.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'type' => 'required|in:pick,pack,putaway',
            'priority' => 'nullable|integer|min:1|max:5',
            'source_type' => 'nullable|in:sales_order,transfer,manual',
            'source_id' => 'nullable|integer',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|integer|exists:inventory_products,id',
            'lines.*.location_id' => 'required|integer|exists:inventory_locations,id',
            'lines.*.quantity_requested' => 'required|numeric|min:0.01',
        ]);

        $po = $this->service->createPickingOrder(
            $data['lines'],
            $data['type'],
            (int) $data['warehouse_id']
        );

        if (isset($data['priority'])) {
            $po->update(['priority' => $data['priority']]);
        }

        if (isset($data['source_type'])) {
            $po->update(['source_type' => $data['source_type'], 'source_id' => $data['source_id'] ?? null]);
        }

        return response()->json($po->load('lines.product:id,name,sku', 'lines.location:id,name,code'), 201);
    }

    /**
     * Show a picking order.
     */
    public function show(PickingOrder $pickingOrder): JsonResponse
    {
        return response()->json(
            $pickingOrder->load([
                'warehouse:id,name',
                'assignee:id,name',
                'lines.product:id,name,sku,barcode',
                'lines.location:id,name,code',
            ])
        );
    }

    /**
     * Update a picking order.
     */
    public function update(Request $request, PickingOrder $pickingOrder): JsonResponse
    {
        $data = $request->validate([
            'priority' => 'nullable|integer|min:1|max:5',
            'status' => 'nullable|in:pending,in_progress,cancelled',
        ]);

        $pickingOrder->update(array_filter($data));

        return response()->json($pickingOrder->refresh());
    }

    /**
     * Delete a picking order.
     */
    public function destroy(PickingOrder $pickingOrder): JsonResponse
    {
        abort_if(
            in_array($pickingOrder->status, ['in_progress', 'completed'], true),
            422,
            'Cannot delete an active or completed picking order.'
        );

        $pickingOrder->delete();

        return response()->json(null, 204);
    }

    /**
     * Assign a picker to a picking order.
     */
    public function assign(Request $request, PickingOrder $pickingOrder): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::findOrFail($data['user_id']);
        $this->service->assignPicker($pickingOrder, $user);

        return response()->json($pickingOrder->refresh()->load(['assignee:id,name']));
    }

    /**
     * Record a pick for a line.
     */
    public function pickLine(Request $request, PickingOrder $pickingOrder, PickingLine $pickingLine): JsonResponse
    {
        abort_if($pickingLine->picking_order_id !== $pickingOrder->id, 404);

        $data = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
        ]);

        $this->service->recordPick($pickingLine, (float) $data['quantity']);

        return response()->json($pickingLine->refresh());
    }

    /**
     * Complete a picking order.
     */
    public function complete(PickingOrder $pickingOrder): JsonResponse
    {
        abort_if($pickingOrder->status === 'completed', 422, 'Already completed.');

        $this->service->completePicking($pickingOrder);

        return response()->json($pickingOrder);
    }

    /**
     * Get next pick line (mobile-optimized).
     */
    public function nextPick(PickingOrder $pickingOrder): JsonResponse
    {
        $line = $this->service->getNextPickLine($pickingOrder);

        if (! $line instanceof PickingLine) {
            return response()->json(['message' => 'No more items to pick.'], 200);
        }

        return response()->json($line->load(['product', 'location.warehouse:id,name']));
    }

    /**
     * Get the next unassigned pending picking order for the current picker.
     */
    public function next(Request $request): JsonResponse
    {
        $order = PickingOrder::with(['warehouse:id,name'])
            ->withCount('lines')
            ->where('status', 'pending')
            ->whereNull('assigned_to')
            ->when($request->input('warehouse_id'), fn ($q, $v) => $q->where('warehouse_id', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->orderBy('priority')
            ->oldest()
            ->first();

        if (! $order) {
            return response()->json(['message' => 'No pending picking orders.'], 200);
        }

        return response()->json($order);
    }
}
