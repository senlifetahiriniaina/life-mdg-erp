<?php

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Requests\StoreWarehouseRequest;
use Modules\Inventory\Http\Requests\UpdateWarehouseRequest;
use Modules\Inventory\Http\Resources\WarehouseResource;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;

/**
 * @group Controllers - Warehouse
 *
 * Manage Warehouse resources.
 */
class WarehouseController extends Controller
{
    public function __construct(protected InventoryService $service) {}

    public function index(Request $request)
    {
        $search = $request->query('search');
        $perPage = $request->query('per_page', 15);

        $query = Warehouse::withCount('stockMovements');

        if ($search) {
            $query->where('name', 'LIKE', "%$search%")
                ->orWhere('city', 'LIKE', "%$search%");
        }

        $warehouses = $query->paginate($perPage);

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request)
    {
        $warehouse = $this->service->createWarehouse($request->validated());

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }

    public function show(Warehouse $warehouse)
    {
        $warehouse->load('stockMovements');

        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $updated = $this->service->updateWarehouse($warehouse, $request->validated());

        return new WarehouseResource($updated);
    }

    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return response()->noContent();
    }
}
