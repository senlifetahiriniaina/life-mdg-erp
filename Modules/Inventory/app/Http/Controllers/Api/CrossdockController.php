<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\CrossdockOperation;
use Modules\Inventory\Services\CrossdockService;

/**
 * @group Controllers - Crossdock
 *
 * Manage Crossdock resources.
 */
class CrossdockController extends Controller
{
    use ScopesToCompany;

    public function __construct(private readonly CrossdockService $service) {}

    public function index(Request $request): JsonResponse
    {
        $operations = CrossdockOperation::with('product')
            ->where('company_id', $this->companyId($request))
            ->latest()
            ->paginate(20);

        return response()->json($operations);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inbound_shipment_id' => 'nullable|integer',
            'outbound_order_id' => 'nullable|integer',
            'product_id' => 'required|exists:inventory_products,id',
            'qty' => 'required|numeric|min:0.01',
        ]);

        $op = $this->service->planCrossdock($data);

        // company_id is never trusted from client input — always the
        // authenticated caller's own, set server-side after creation.
        $op->update(['company_id' => $this->companyId($request)]);
        $op->load('product');

        return response()->json($op, 201);
    }

    public function show(Request $request, CrossdockOperation $crossdockOperation): JsonResponse
    {
        $this->assertSameCompany($request, $crossdockOperation);
        $crossdockOperation->load('product');

        return response()->json($crossdockOperation);
    }

    public function update(Request $request, CrossdockOperation $crossdockOperation): JsonResponse
    {
        $this->assertSameCompany($request, $crossdockOperation);

        $data = $request->validate([
            'inbound_shipment_id' => 'nullable|integer',
            'outbound_order_id' => 'nullable|integer',
            'qty' => 'nullable|numeric|min:0.01',
        ]);

        $crossdockOperation->update($data);

        return response()->json($crossdockOperation);
    }

    public function destroy(Request $request, CrossdockOperation $crossdockOperation): JsonResponse
    {
        $this->assertSameCompany($request, $crossdockOperation);

        if ($crossdockOperation->status === 'executed') {
            return response()->json(['message' => 'Cannot delete an executed operation.'], 422);
        }

        $crossdockOperation->delete();

        return response()->json(null, 204);
    }

    public function execute(Request $request, CrossdockOperation $crossdockOperation): JsonResponse
    {
        $this->assertSameCompany($request, $crossdockOperation);
        $this->service->execute($crossdockOperation);
        $crossdockOperation->load('product');

        return response()->json($crossdockOperation);
    }

    public function suggestions(): JsonResponse
    {
        $suggestions = $this->service->getSuggestions();

        return response()->json($suggestions);
    }
}
