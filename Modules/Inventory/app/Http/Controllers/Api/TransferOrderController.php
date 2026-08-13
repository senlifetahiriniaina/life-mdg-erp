<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\RedistributionRule;
use Modules\Inventory\Models\TransferOrder;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\StockRedistributionService;

/**
 * @group Controllers - Transfer Order
 *
 * Manage Transfer Order resources.
 */
class TransferOrderController extends Controller
{
    public function __construct(private readonly StockRedistributionService $service) {}

    public function pending(): JsonResponse
    {
        return response()->json($this->service->pendingTransfers());
    }

    public function index(Request $request): JsonResponse
    {
        $query = TransferOrder::with(['fromWarehouse', 'toWarehouse'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->latest();

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:inventory_warehouses,id',
            'to_warehouse_id' => 'required|exists:inventory_warehouses,id|different:from_warehouse_id',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:inventory_products,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $order = $this->service->createTransferOrder(
            $validated['from_warehouse_id'],
            $validated['to_warehouse_id'],
            $validated['lines'],
            [
                'priority' => $validated['priority'] ?? 'normal',
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'requested_by' => $request->user()->id,
            ]
        );

        return response()->json($order, 201);
    }

    public function show(TransferOrder $order): JsonResponse
    {
        $order->load(['lines.product', 'fromWarehouse', 'toWarehouse']);

        return response()->json($order->toArray());
    }

    public function update(Request $request, TransferOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $order->update($validated);

        return response()->json($order->fresh());
    }

    public function approve(Request $request, TransferOrder $order): JsonResponse
    {
        $order = $this->service->approveTransfer($order, $request->user()->id);

        return response()->json($order);
    }

    public function ship(TransferOrder $order): JsonResponse
    {
        $order = $this->service->shipTransfer($order);

        return response()->json($order);
    }

    public function receive(Request $request, TransferOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'received_quantities' => 'nullable|array',
            'received_quantities.*' => 'numeric|min:0',
        ]);

        $order = $this->service->receiveTransfer($order, $validated['received_quantities'] ?? []);

        return response()->json($order->load('lines'));
    }

    public function cancel(TransferOrder $order): JsonResponse
    {
        $order = $this->service->cancelTransfer($order);

        return response()->json($order);
    }

    // ─── Redistribution Rules ─────────────────────────────────────────────────

    public function indexRules(): JsonResponse
    {
        return response()->json(RedistributionRule::with(['fromWarehouse', 'toWarehouse', 'product'])->get());
    }

    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'from_warehouse_id' => 'nullable|exists:inventory_warehouses,id',
            'to_warehouse_id' => 'nullable|exists:inventory_warehouses,id',
            'product_id' => 'nullable|exists:inventory_products,id',
            'rule_type' => 'required|in:min_stock,max_stock,reorder_point,demand_based',
            'trigger_threshold' => 'required|numeric|min:0',
            'transfer_quantity' => 'required|numeric|min:0.0001',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer|min:1',
        ]);

        return response()->json(RedistributionRule::create($validated), 201);
    }

    public function updateRule(Request $request, RedistributionRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'trigger_threshold' => 'sometimes|numeric|min:0',
            'transfer_quantity' => 'sometimes|numeric|min:0.0001',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:1',
        ]);

        $rule->update($validated);

        return response()->json($rule->fresh());
    }

    public function deleteRule(RedistributionRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(['message' => 'Rule deleted']);
    }

    // ─── Analysis & Suggestions ───────────────────────────────────────────────

    public function autoSuggest(): JsonResponse
    {
        $result = $this->service->autoSuggestTransfers();

        return response()->json($result);
    }

    public function rebalancingAnalysis(): JsonResponse
    {
        return response()->json($this->service->rebalancingAnalysis());
    }

    public function transferHistory(Request $request, Warehouse $warehouse): JsonResponse
    {
        $limit = min(100, (int) $request->input('limit', 50));
        $history = $this->service->warehouseTransferHistory($warehouse->id, $limit);

        return response()->json($history);
    }
}
