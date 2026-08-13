<?php

namespace Modules\Achats\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Achats\Models\PurchaseOrder;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('Achats/PurchaseOrders/Index');
    }

    public function create()
    {
        return Inertia::render('Achats/PurchaseOrders/Form');
    }

    public function show(PurchaseOrder $purchase_order)
    {
        return Inertia::render('Achats/PurchaseOrders/Show', [
            'purchaseOrder' => $purchase_order->load(['supplier', 'lines', 'requester', 'approver']),
        ]);
    }

    public function edit(PurchaseOrder $purchase_order)
    {
        return Inertia::render('Achats/PurchaseOrders/Form', [
            'purchaseOrder' => $purchase_order,
        ]);
    }
}
