<?php

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\RecordStockMovementRequest;
use Modules\Inventory\Http\Resources\StockMovementResource;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;

/**
 * @group Controllers - Stock Movement
 *
 * Manage Stock Movement resources.
 */
class StockMovementController extends Controller
{
    public function __construct(protected InventoryService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', StockMovement::class);

        $productId = $request->query('product_id');
        $warehouseId = $request->query('warehouse_id');
        $type = $request->query('type');
        $perPage = $request->query('per_page', 50);

        $query = StockMovement::query();

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $movements = $query->with('product', 'warehouse')
            ->recent()
            ->paginate($perPage);

        return StockMovementResource::collection($movements);
    }

    public function store(RecordStockMovementRequest $request)
    {
        $this->authorize('create', StockMovement::class);

        $movement = $this->service->recordStockMovement($request->validated());

        return response()->json(new StockMovementResource($movement), 201);
    }

    /**
     * Chantier 10: routed via apiResource(['index', 'show']) but this method
     * never existed — GET stock-movements/{stock_movement} was a fatal "call
     * to undefined method" on every request. update()/destroy() were also
     * routed with no matching method at all; those were dropped from the
     * route registration instead of stubbed out, since a stock movement is
     * an immutable audit-trail fact in this design (Stock/Movements.vue only
     * ever GETs and POSTs, never PUTs/DELETEs one) and no frontend page
     * anywhere calls either verb.
     */
    public function show(StockMovement $stockMovement)
    {
        $this->authorize('view', $stockMovement);

        return new StockMovementResource($stockMovement->load('product', 'warehouse'));
    }

    public function productHistory(Product $product, Request $request)
    {
        $limit = $request->query('limit', 50);
        $movements = $this->service->getStockHistory($product, $limit);

        return StockMovementResource::collection($movements);
    }
}
