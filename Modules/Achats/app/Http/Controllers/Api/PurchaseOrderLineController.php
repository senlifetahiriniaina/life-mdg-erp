<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Achats\Http\Controllers\Api\Concerns\ScopesToCompany;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;
use Modules\Achats\Services\PurchaseOrderService;

/**
 * @group Controllers - Purchase Order Line
 *
 * Manage Purchase Order Line resources. This nested REST surface
 * (`purchase-orders/{po}/lines[/{line}]`) is a genuinely separate,
 * API-First-principle path from PurchaseOrderController::store()/update(),
 * which the real Form.vue page uses for whole-PO submits (Chantier 10) —
 * neither one is a redundant duplicate of the other: this one lets an API
 * consumer manage a single PO's lines incrementally without resubmitting
 * the whole order. Both share the same underlying, already-tested
 * PurchaseOrderService methods, so there's no risk of the two paths
 * drifting apart on business rules.
 */
class PurchaseOrderLineController extends Controller
{
    use AuthorizesRequests;
    use ScopesToCompany;

    public function __construct(protected PurchaseOrderService $service) {}

    /**
     * Chantier 32.13 (layer 6, security deep — a real IDOR, confirmed
     * empirically): show()/update()/destroy() only ever checked that the
     * URL's {purchase_order} belonged to the caller's company — never that
     * the URL's {line} actually belongs to THAT SPECIFIC purchase order.
     * Since PurchaseOrderLine has no company_id of its own, a caller could
     * put any PO they own in the URL slot and pass ANY line id — including
     * one belonging to a completely different company's purchase order —
     * and view/edit/delete it. Confirmed via a real HTTP request: PUT
     * purchase-orders/{ownPo}/lines/{anotherPosLine} returned 200 and
     * silently updated a line the caller had no real relationship to at
     * all. Same-shape fix applied to RFQLineController below.
     */
    private function assertLineBelongsToOrder(PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line): void
    {
        abort_unless($purchase_order_line->purchase_order_id === $purchase_order->id, 404);
    }

    public function index(Request $request, PurchaseOrder $purchase_order)
    {
        $this->assertSameCompany($request, $purchase_order);

        return $purchase_order->lines()->get();
    }

    /**
     * Chantier 10: was a literal "// Implementation to follow" stub —
     * authorized the call then did nothing. Wired onto the real
     * PurchaseOrderService::addLineItem(), which already enforces
     * "only a draft PO can be edited" and computes line_total server-side.
     */
    public function store(Request $request, PurchaseOrder $purchase_order)
    {
        $this->authorize('create', PurchaseOrderLine::class);
        $this->assertSameCompany($request, $purchase_order);

        $data = $request->validate([
            'product_id' => 'nullable|exists:inventory_products,id',
            'description' => 'required|string',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'nullable|string',
            'unit_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0',
        ]);

        $line = $this->service->addLineItem($purchase_order, $data);

        return response()->json($line, 201);
    }

    public function show(Request $request, PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        $this->assertSameCompany($request, $purchase_order);
        $this->assertLineBelongsToOrder($purchase_order, $purchase_order_line);

        return $purchase_order_line;
    }

    /**
     * Chantier 10: was a stub. PurchaseOrderService has no dedicated
     * "update a line" method (only add), so this mirrors the same
     * draft-only guard addLineItem() enforces, applied directly to a plain
     * model update plus the same line_total recomputation addLineItem()
     * does — matching the service's existing business rule rather than
     * inventing a new one.
     */
    public function update(Request $request, PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        $this->authorize('update', $purchase_order_line);
        $this->assertSameCompany($request, $purchase_order);
        $this->assertLineBelongsToOrder($purchase_order, $purchase_order_line);

        abort_if(! $purchase_order->isDraft(), 422, 'Cannot update lines on a non-draft purchase order');

        $data = $request->validate([
            'product_id' => 'nullable|exists:inventory_products,id',
            'description' => 'sometimes|string',
            'quantity' => 'sometimes|numeric|min:0.01',
            'unit' => 'nullable|string',
            'unit_price' => 'sometimes|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0',
        ]);

        $quantity = $data['quantity'] ?? $purchase_order_line->quantity;
        $unitPrice = $data['unit_price'] ?? $purchase_order_line->unit_price;
        $data['line_total'] = $quantity * $unitPrice;

        $purchase_order_line->update($data);

        return $purchase_order_line;
    }

    /**
     * Chantier 10: was a stub.
     */
    public function destroy(Request $request, PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        $this->authorize('delete', $purchase_order_line);
        $this->assertSameCompany($request, $purchase_order);
        $this->assertLineBelongsToOrder($purchase_order, $purchase_order_line);

        abort_if(! $purchase_order->isDraft(), 422, 'Cannot remove lines from a non-draft purchase order');

        $purchase_order_line->delete();

        return response()->noContent();
    }
}
