<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Services\PurchaseOrderService;

/**
 * @group Inventory - Purchase Orders
 *
 * Inventory's OWN purchase order concept (`inventory_purchase_orders`) —
 * distinct from Achats' `achats_purchase_orders`/`PurchaseOrder`, which has
 * its own separate, already-scoped ScopesToCompany trait/controller. Do not
 * confuse the two same-short-name classes.
 */
class PurchaseOrderController extends Controller
{
    use ScopesToCompany;

    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $pos = $this->scopeToCompany(PurchaseOrder::with('supplier:id,name', 'warehouse:id,name'), $request)
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('supplier_id'), fn ($q, $v) => $q->where('supplier_id', $v))
            ->latest()
            ->paginate(20);

        return response()->json($pos);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:inventory_suppliers,id',
            'warehouse_id' => 'nullable|integer',
            'currency' => 'nullable|string|size:3',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'expected_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_name' => 'required|string',
            'items.*.sku' => 'nullable|string',
            'items.*.quantity_ordered' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $items = $data['items'];
        // company_id is never trusted from client input — always the
        // authenticated caller's own. Safe to include in $poData here since
        // PurchaseOrderService::createPO() spreads the whole array onto
        // PurchaseOrder::create(), unlike this module's other services.
        $poData = array_merge(
            array_diff_key($data, ['items' => true]),
            [
                'created_by' => $request->user()->id,
                'company_id' => $this->companyId($request),
            ]
        );

        $po = $this->service->createPO($poData, $items);

        return response()->json($po, 201);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSameCompany($request, $purchaseOrder);

        return response()->json($purchaseOrder->load('supplier', 'warehouse', 'items', 'creator:id,name'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSameCompany($request, $purchaseOrder);
        abort_if(in_array($purchaseOrder->status, ['received', 'cancelled'], true), 422, 'Cannot edit a received or cancelled PO.');

        $data = $request->validate([
            'notes' => 'nullable|string',
            'expected_at' => 'nullable|date',
            'status' => 'sometimes|in:draft,sent,confirmed,cancelled',
        ]);

        $purchaseOrder->update($data);

        return response()->json($purchaseOrder);
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSameCompany($request, $purchaseOrder);
        abort_if($purchaseOrder->status !== 'draft', 422, 'Only draft POs can be sent.');
        $this->service->send($purchaseOrder);

        return response()->json($purchaseOrder->fresh());
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSameCompany($request, $purchaseOrder);
        abort_if(! in_array($purchaseOrder->status, ['sent', 'confirmed', 'partial'], true), 422, 'PO is not in a receivable state.');

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0',
        ]);

        $qtys = collect($data['items'])->pluck('qty', 'id')->all();
        $this->service->receive($purchaseOrder, $qtys);

        return response()->json($purchaseOrder->fresh(['items']));
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertSameCompany($request, $purchaseOrder);
        abort_if($purchaseOrder->status !== 'draft', 422, 'Only draft POs can be deleted.');
        $purchaseOrder->delete();

        return response()->json(null, 204);
    }
}
