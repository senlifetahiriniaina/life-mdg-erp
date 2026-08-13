<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;

/**
 * @group Controllers - Purchase Order Line
 *
 * Manage Purchase Order Line resources.
 */
class PurchaseOrderLineController extends Controller
{
    public function index(PurchaseOrder $purchase_order)
    {
        return $purchase_order->lines()->get();
    }

    public function store(PurchaseOrder $purchase_order)
    {
        // Implementation to follow
    }

    public function show(PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        return $purchase_order_line;
    }

    public function update(PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        // Implementation to follow
    }

    public function destroy(PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        // Implementation to follow
    }
}
