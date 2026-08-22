<?php

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
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
    use ScopesToCompany;

    public function __construct(protected InventoryService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Warehouse::class);

        $search = $request->query('search');
        $perPage = $request->query('per_page', 15);

        $query = $this->scopeToCompany(Warehouse::withCount('stockMovements'), $request);

        if ($search) {
            // Chantier 32.22: was an unguarded top-level orWhere('city', ...)
            // — combined with the company scope above, operator precedence
            // made it `(company_id = ? AND name LIKE ?) OR city LIKE ?`,
            // defeating the tenant scope for any city-matching row of any
            // company. Wrapped in a closure so both search branches stay
            // inside the scoped AND group.
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%")
                    ->orWhere('city', 'LIKE', "%$search%");
            });
        }

        $warehouses = $query->paginate($perPage);

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request)
    {
        $this->authorize('create', Warehouse::class);

        $data = $request->validated();
        $data['company_id'] = $this->companyId($request);

        $warehouse = $this->service->createWarehouse($data);

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }

    public function show(Request $request, Warehouse $warehouse)
    {
        $this->authorize('view', $warehouse);
        $this->assertSameCompany($request, $warehouse);

        $warehouse->load('stockMovements');

        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $this->authorize('update', $warehouse);
        $this->assertSameCompany($request, $warehouse);

        $updated = $this->service->updateWarehouse($warehouse, $request->validated());

        return new WarehouseResource($updated);
    }

    public function destroy(Request $request, Warehouse $warehouse)
    {
        $this->authorize('delete', $warehouse);
        $this->assertSameCompany($request, $warehouse);

        $warehouse->delete();

        return response()->noContent();
    }
}
