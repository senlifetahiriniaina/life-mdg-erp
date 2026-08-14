<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Achats\Http\Requests\StorePurchaseOrderRequest;
use Modules\Achats\Http\Requests\UpdatePurchaseOrderRequest;
use Modules\Achats\Http\Resources\PurchaseOrderResource;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Services\PurchaseOrderService;

/**
 * @group Controllers - Purchase Order
 *
 * Manage purchase orders.
 */
class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected PurchaseOrderService $service) {}

    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'requester', 'approver']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('order_date', [$request->date_from, $request->date_to]);
        }

        $pos = $query->paginate($request->get('per_page', 15));

        return PurchaseOrderResource::collection($pos);
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $po = $this->service->createPurchaseOrder($data);

        return new PurchaseOrderResource($po);
    }

    public function show(PurchaseOrder $purchase_order)
    {
        $purchase_order->load(['supplier', 'lines', 'requester', 'approver', 'receipt']);

        return new PurchaseOrderResource($purchase_order);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        $po = $this->service->updatePurchaseOrder($purchase_order, $request->validated());

        return new PurchaseOrderResource($po);
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        $this->service->cancelPurchaseOrder($purchase_order, 'Deleted by user');

        return response()->noContent();
    }

    public function submitForApproval(Request $request, PurchaseOrder $purchase_order)
    {
        $this->authorize('update', $purchase_order);

        $this->service->submitForApproval($purchase_order, auth()->user());

        return new PurchaseOrderResource($purchase_order->refresh());
    }

    public function approve(Request $request, PurchaseOrder $purchase_order)
    {
        $this->authorize('approve', $purchase_order);

        $this->service->markAsApproved($purchase_order, auth()->user());

        return new PurchaseOrderResource($purchase_order->refresh());
    }

    public function reject(Request $request, PurchaseOrder $purchase_order)
    {
        $this->authorize('reject', $purchase_order);

        $this->service->markAsRejected($purchase_order, auth()->user(), $request->get('reason', 'Rejeté'));

        return new PurchaseOrderResource($purchase_order->refresh());
    }

    public function cancel(Request $request, PurchaseOrder $purchase_order)
    {
        $this->authorize('delete', $purchase_order);

        $this->service->cancelPurchaseOrder($purchase_order, $request->get('reason', 'Cancelled'));

        return new PurchaseOrderResource($purchase_order->refresh());
    }
}
