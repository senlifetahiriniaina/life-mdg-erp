<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\Supplier;

/**
 * @group Inventory - Suppliers
 */
class SupplierController extends Controller
{
    use ScopesToCompany;

    public function index(Request $request): JsonResponse
    {
        $suppliers = $this->scopeToCompany(Supplier::query(), $request)
            ->when($request->input('search'), fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->withCount('purchaseOrders')
            ->latest()
            ->paginate(20);

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|unique:inventory_suppliers,code',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'website' => 'nullable|url',
            'address' => 'nullable|string',
            'country' => 'nullable|string|size:2',
            'currency' => 'nullable|string|size:3',
            'payment_terms' => 'nullable|in:net15,net30,net60,prepaid',
            'lead_time_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $data['company_id'] = $this->companyId($request);

        $supplier = Supplier::create($data);

        return response()->json($supplier, 201);
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        $this->assertSameCompany($request, $supplier);

        return response()->json($supplier->load('purchaseOrders'));
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $this->assertSameCompany($request, $supplier);

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'payment_terms' => 'nullable|in:net15,net30,net60,prepaid',
            'lead_time_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $supplier->update($data);

        return response()->json($supplier);
    }

    public function destroy(Request $request, Supplier $supplier): JsonResponse
    {
        $this->assertSameCompany($request, $supplier);

        $supplier->delete();

        return response()->json(null, 204);
    }
}
