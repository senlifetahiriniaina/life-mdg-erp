<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Achats\Http\Requests\StoreSupplierRequest;
use Modules\Achats\Http\Requests\UpdateSupplierRequest;
use Modules\Achats\Http\Resources\SupplierResource;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Services\SupplierService;

/**
 * @group Controllers - Supplier
 *
 * Manage suppliers and vendors.
 */
class SupplierController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected SupplierService $service) {}

    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }

        $suppliers = $query->paginate($request->get('per_page', 15));

        return SupplierResource::collection($suppliers);
    }

    public function store(StoreSupplierRequest $request)
    {
        $this->authorize('create', Supplier::class);

        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $supplier = $this->service->createSupplier($data);

        return response()->json((new SupplierResource($supplier))->resolve(), 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json((new SupplierResource($supplier))->resolve());
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);

        $updated = $this->service->updateSupplier($supplier, $request->validated());

        return new SupplierResource($updated);
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('delete', $supplier);

        try {
            $this->service->deleteSupplier($supplier);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->noContent();
    }

    public function performanceMetrics(Supplier $supplier)
    {
        $metrics = $this->service->getSupplierPerformanceMetrics($supplier);

        return response()->json([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'metrics' => $metrics,
        ]);
    }

    public function quoteHistory(Request $request, Supplier $supplier)
    {
        $quotes = $this->service->getSupplierQuoteHistory($supplier);

        return response()->json($quotes);
    }
}
