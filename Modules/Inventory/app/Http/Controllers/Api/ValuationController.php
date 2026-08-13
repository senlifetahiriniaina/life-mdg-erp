<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\ValuationRun;
use Modules\Inventory\Services\ValuationService;

/**
 * @group Controllers - Valuation
 *
 * Manage Valuation resources.
 */
class ValuationController extends Controller
{
    public function __construct(private readonly ValuationService $service) {}

    /**
     * GET /api/v1/inventory/valuation/runs
     */
    public function indexRuns(): JsonResponse
    {
        $runs = ValuationRun::orderBy('created_at', 'desc')->paginate(20);

        return response()->json($runs);
    }

    /**
     * POST /api/v1/inventory/valuation/run
     */
    public function runValuation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'method' => 'nullable|in:fifo,weighted_average',
        ]);

        $run = $this->service->runValuation(
            $validated['name'],
            $validated['method'] ?? 'fifo'
        );

        return response()->json($run, 201);
    }

    /**
     * GET /api/v1/inventory/valuation/runs/{run}
     */
    public function showRun(ValuationRun $run): JsonResponse
    {
        return response()->json($run);
    }

    /**
     * POST /api/v1/inventory/valuation/receive
     */
    public function receiveCostLayer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:inventory_products,id',
            'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'quantity' => 'required|numeric|min:0.0001',
            'unit_cost' => 'required|numeric|min:0',
            'method' => 'nullable|in:fifo,weighted_average',
            'reference' => 'nullable|string|max:100',
        ]);

        $layer = $this->service->receiveCostLayer(
            (int) $validated['product_id'],
            (int) $validated['warehouse_id'],
            (float) $validated['quantity'],
            (float) $validated['unit_cost'],
            $validated['method'] ?? 'fifo',
            $validated['reference'] ?? ''
        );

        return response()->json($layer, 201);
    }

    /**
     * GET /api/v1/inventory/valuation/product-value?product_id=&warehouse_id=
     */
    public function productValue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:inventory_products,id',
            'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
        ]);

        $value = $this->service->getProductValue(
            (int) $validated['product_id'],
            (int) $validated['warehouse_id']
        );

        return response()->json($value);
    }

    /**
     * GET /api/v1/inventory/valuation/total-value
     */
    public function totalValue(): JsonResponse
    {
        return response()->json(['total_value' => $this->service->getTotalInventoryValue()]);
    }

    /**
     * GET /api/v1/inventory/valuation/summary
     */
    public function summary(): JsonResponse
    {
        return response()->json($this->service->getValuationSummary());
    }

    /**
     * GET /api/v1/inventory/valuation/by-warehouse
     */
    public function byWarehouse(): JsonResponse
    {
        return response()->json($this->service->getValueByWarehouse());
    }

    /**
     * GET /api/v1/inventory/valuation/layers?product_id=&warehouse_id=
     */
    public function activeLayers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:inventory_products,id',
            'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
        ]);

        $layers = $this->service->getActiveLayers(
            (int) $validated['product_id'],
            (int) $validated['warehouse_id']
        );

        return response()->json($layers);
    }
}
