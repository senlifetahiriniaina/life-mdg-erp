<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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

    /**
     * Chantier 10: the headline Achats finding — PurchaseOrders/Form.vue
     * submits the whole form (header fields + a `lines` array) as one
     * request body, but this method previously dropped `lines` entirely
     * (StorePurchaseOrderRequest had no rule for it, and
     * PurchaseOrderService::createPurchaseOrder() never read it) — every
     * real PO created through the UI silently ended up with zero line
     * items. Wired onto the real, already-tested, previously-unused
     * PurchaseOrderService::addLineItem().
     */
    public function store(StorePurchaseOrderRequest $request)
    {
        $data = $request->validated();
        $lines = $data['lines'] ?? [];
        unset($data['lines']);
        $data['created_by'] = auth()->id();

        $po = $this->service->createPurchaseOrder($data);

        foreach ($lines as $lineData) {
            $this->service->addLineItem($po, $lineData);
        }

        return new PurchaseOrderResource($po->load('lines'));
    }

    public function show(PurchaseOrder $purchase_order)
    {
        $purchase_order->load(['supplier', 'lines', 'requester', 'approver', 'receipt']);

        return new PurchaseOrderResource($purchase_order);
    }

    /**
     * Chantier 10: same silent-data-loss bug as store() on edit. The
     * frontend has no per-line id tracking across edits (Form.vue just
     * Object.assign()s show()'s response then free-form pushes/splices),
     * so the lowest-risk fix matching that shape is delete-and-recreate:
     * only touch lines at all when the request actually sent a `lines`
     * key, then replace the set wholesale via the same real
     * addLineItem() service method store() uses.
     */
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        $data = $request->validated();
        $hasLines = array_key_exists('lines', $data);
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $po = $this->service->updatePurchaseOrder($purchase_order, $data);

        if ($hasLines) {
            $po->lines()->delete();
            foreach ($lines as $lineData) {
                $this->service->addLineItem($po, $lineData);
            }
        }

        return new PurchaseOrderResource($po->load('lines'));
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
