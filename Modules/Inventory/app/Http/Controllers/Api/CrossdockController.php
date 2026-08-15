<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Models\CrossdockOperation;
use Modules\Inventory\Services\CrossdockService;

/**
 * @group Controllers - Crossdock
 *
 * Manage Crossdock resources.
 */
class CrossdockController extends Controller
{
    public function __construct(private readonly CrossdockService $service) {}

    public function index(): JsonResponse
    {
        $operations = CrossdockOperation::with('product')->latest()->paginate(20);

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
        $op->load('product');

        return response()->json($op, 201);
    }

    public function show(CrossdockOperation $crossdockOperation): JsonResponse
    {
        $crossdockOperation->load('product');

        return response()->json($crossdockOperation);
    }

    public function update(Request $request, CrossdockOperation $crossdockOperation): JsonResponse
    {
        $data = $request->validate([
            'inbound_shipment_id' => 'nullable|integer',
            'outbound_order_id' => 'nullable|integer',
            'qty' => 'nullable|numeric|min:0.01',
        ]);

        $crossdockOperation->update($data);

        return response()->json($crossdockOperation);
    }

    public function destroy(CrossdockOperation $crossdockOperation): JsonResponse
    {
        if ($crossdockOperation->status === 'executed') {
            return response()->json(['message' => 'Cannot delete an executed operation.'], 422);
        }

        $crossdockOperation->delete();

        return response()->json(null, 204);
    }

    public function execute(CrossdockOperation $crossdockOperation): JsonResponse
    {
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
