<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Achats\Http\Controllers\Api\Concerns\ScopesToCompany;
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
    use ScopesToCompany;

    public function __construct(protected SupplierService $service) {}

    public function index(Request $request)
    {
        // Chantier 19: had zero company scoping — any authenticated user
        // could list every other company's suppliers.
        $query = Supplier::where('company_id', $this->companyId($request));

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            // Chantier 32.13 (layer 6, security deep — a real cross-tenant
            // leak, confirmed empirically): the un-grouped ->orWhere() calls
            // broke out of the company_id constraint entirely — Laravel
            // built `WHERE company_id = ? AND name LIKE ? OR code LIKE ?
            // OR email LIKE ?`, so searching from company A's own context
            // for a code/email substring unique to company B's supplier
            // returned company B's real row. Grouped in a closure so the
            // OR only ever applies within the already-scoped result set.
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->paginate($request->get('per_page', 15));

        return SupplierResource::collection($suppliers);
    }

    public function store(StoreSupplierRequest $request)
    {
        $this->authorize('create', Supplier::class);

        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['company_id'] = $this->companyId($request);

        $supplier = $this->service->createSupplier($data);

        return response()->json((new SupplierResource($supplier))->resolve(), 201);
    }

    public function show(Request $request, Supplier $supplier)
    {
        $this->assertSameCompany($request, $supplier);

        return response()->json((new SupplierResource($supplier))->resolve());
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);
        $this->assertSameCompany($request, $supplier);

        $updated = $this->service->updateSupplier($supplier, $request->validated());

        return new SupplierResource($updated);
    }

    public function destroy(Request $request, Supplier $supplier)
    {
        $this->authorize('delete', $supplier);
        $this->assertSameCompany($request, $supplier);

        try {
            $this->service->deleteSupplier($supplier);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->noContent();
    }

    public function performanceMetrics(Request $request, Supplier $supplier)
    {
        $this->assertSameCompany($request, $supplier);

        $metrics = $this->service->getSupplierPerformanceMetrics($supplier);

        return response()->json([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'metrics' => $metrics,
        ]);
    }

    public function quoteHistory(Request $request, Supplier $supplier)
    {
        $this->assertSameCompany($request, $supplier);

        $quotes = $this->service->getSupplierQuoteHistory($supplier);

        return response()->json($quotes);
    }
}
