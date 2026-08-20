<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Requests\StoreProductionOrderRequest;
use Modules\Inventory\Http\Requests\UpdateProductionOrderRequest;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Inventory\Services\ProductionOrderService;
use Modules\Inventory\Services\TraceabilityService;

/**
 * Chantier 23 (volet C de la feuille de route Chantier 21) — commande de
 * production simplifiée : suivi du statut d'un article entre "matières
 * réunies" et "livré", en passant par la sous-traitance de production.
 */
class ProductionOrderController extends Controller
{
    private const WITH = ['costingSheet:id,reference,name', 'subcontractor:id,name'];

    public function __construct(
        private readonly ProductionOrderService $service,
        private readonly TraceabilityService $traceability,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductionOrder::class);

        $orders = ProductionOrder::query()
            ->with(self::WITH)
            ->when($request->filled('status'), fn ($q) => $q->status($request->string('status')))
            ->when($request->filled('costing_sheet_id'), fn ($q) => $q->where('costing_sheet_id', $request->integer('costing_sheet_id')))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($orders);
    }

    public function show(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('view', $productionOrder);

        return response()->json(['data' => $productionOrder->load(self::WITH)]);
    }

    public function store(StoreProductionOrderRequest $request): JsonResponse
    {
        $this->authorize('create', ProductionOrder::class);

        $order = $this->service->create($request->validated(), $request->user()->id);

        return response()->json(['data' => $order->load(self::WITH)], 201);
    }

    public function update(UpdateProductionOrderRequest $request, ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('update', $productionOrder);

        $order = $this->service->update($productionOrder, $request->validated());

        return response()->json(['data' => $order->load(self::WITH)]);
    }

    public function destroy(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('delete', $productionOrder);

        $productionOrder->delete();

        return response()->json(null, 204);
    }

    public function transition(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('update', $productionOrder);

        $request->validate(['status' => 'required|string|in:' . implode(',', ProductionOrder::STATUSES)]);

        try {
            $order = $this->service->transition($productionOrder, $request->string('status')->toString());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $order->load(self::WITH)]);
    }

    public function trace(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('view', $productionOrder);

        return response()->json(['data' => $this->traceability->build($productionOrder)]);
    }
}
