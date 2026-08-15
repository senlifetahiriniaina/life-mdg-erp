<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Models\Rma;
use Modules\Inventory\Services\RmaService;

/**
 * @group Controllers - Rma
 *
 * Return merchandise authorizations.
 */
class RmaController extends Controller
{
    public function __construct(private readonly RmaService $service) {}

    public function index(): JsonResponse
    {
        $rmas = Rma::latest()->paginate(20);

        return response()->json($rmas);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => 'nullable|string|unique:inventory_rmas,reference',
            'order_id' => 'nullable|integer',
            'customer_name' => 'required|string|max:255',
            'reason' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01',
            'return_method' => 'nullable|in:refund,exchange,credit',
        ]);

        $rma = $this->service->create($data);

        return response()->json($rma, 201);
    }

    public function show(Rma $rma): JsonResponse
    {
        return response()->json($rma);
    }

    public function update(Request $request, Rma $rma): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'reason' => 'nullable|string',
            'return_method' => 'nullable|in:refund,exchange,credit',
        ]);

        $rma->update($data);

        return response()->json($rma);
    }

    public function destroy(Rma $rma): JsonResponse
    {
        if (! in_array($rma->status, ['requested', 'closed'], true)) {
            return response()->json(['message' => 'Cannot delete an active RMA.'], 422);
        }

        $rma->delete();

        return response()->json(null, 204);
    }

    public function approve(Rma $rma): JsonResponse
    {
        $this->service->approve($rma);
        $rma->refresh();

        return response()->json($rma);
    }

    public function receive(Rma $rma): JsonResponse
    {
        $this->service->receive($rma);
        $rma->refresh();

        return response()->json($rma);
    }

    public function refund(Rma $rma): JsonResponse
    {
        $this->service->processRefund($rma);
        $rma->refresh();

        return response()->json($rma);
    }
}
