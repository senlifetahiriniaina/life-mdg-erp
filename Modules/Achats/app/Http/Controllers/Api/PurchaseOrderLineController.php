<?php

namespace Modules\Achats\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Controller;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;

/**
 * @group Controllers - Purchase Order Line
 *
 * Manage Purchase Order Line resources.
 */
class PurchaseOrderLineController extends Controller
{
    use AuthorizesRequests;

    public function index(PurchaseOrder $purchase_order)
    {
        return $purchase_order->lines()->get();
    }

    public function store(PurchaseOrder $purchase_order)
    {
        $this->authorize('create', PurchaseOrderLine::class);

        // Implementation to follow
    }

    public function show(PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        return $purchase_order_line;
    }

    public function update(PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        $this->authorize('update', $purchase_order_line);

        // Implementation to follow
    }

    public function destroy(PurchaseOrder $purchase_order, PurchaseOrderLine $purchase_order_line)
    {
        $this->authorize('delete', $purchase_order_line);

        // Implementation to follow
    }
}
