<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\Rma;
use Modules\Inventory\Services\RmaService;

/**
 * @group Controllers - Rma
 *
 * Return merchandise authorizations.
 */
class RmaController extends Controller
{
    use ScopesToCompany;

    public function __construct(private readonly RmaService $service) {}

    public function index(Request $request): JsonResponse
    {
        $rmas = Rma::where('company_id', $this->companyId($request))
            ->latest()
            ->paginate(20);

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

        // company_id is never trusted from client input — always the
        // authenticated caller's own, set server-side after creation.
        $rma->update(['company_id' => $this->companyId($request)]);

        return response()->json($rma, 201);
    }

    public function show(Request $request, Rma $rma): JsonResponse
    {
        $this->assertSameCompany($request, $rma);

        return response()->json($rma);
    }

    public function update(Request $request, Rma $rma): JsonResponse
    {
        $this->assertSameCompany($request, $rma);

        $data = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'reason' => 'nullable|string',
            'return_method' => 'nullable|in:refund,exchange,credit',
        ]);

        $rma->update($data);

        return response()->json($rma);
    }

    public function destroy(Request $request, Rma $rma): JsonResponse
    {
        $this->assertSameCompany($request, $rma);

        if (! in_array($rma->status, ['requested', 'closed'], true)) {
            return response()->json(['message' => 'Cannot delete an active RMA.'], 422);
        }

        $rma->delete();

        return response()->json(null, 204);
    }

    public function approve(Request $request, Rma $rma): JsonResponse
    {
        $this->assertSameCompany($request, $rma);
        $this->service->approve($rma);
        $rma->refresh();

        return response()->json($rma);
    }

    public function receive(Request $request, Rma $rma): JsonResponse
    {
        $this->assertSameCompany($request, $rma);
        $this->service->receive($rma);
        $rma->refresh();

        return response()->json($rma);
    }

    public function refund(Request $request, Rma $rma): JsonResponse
    {
        $this->assertSameCompany($request, $rma);
        $this->service->processRefund($rma);
        $rma->refresh();

        return response()->json($rma);
    }
}
