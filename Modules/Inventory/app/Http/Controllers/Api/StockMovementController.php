<?php

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        $movement = $this->service->recordStockMovement($request->validated());

        return response()->json(new StockMovementResource($movement), 201);
    }

    public function productHistory(Product $product, Request $request)
    {
        $limit = $request->query('limit', 50);
        $movements = $this->service->getStockHistory($product, $limit);

        return StockMovementResource::collection($movements);
    }
}
