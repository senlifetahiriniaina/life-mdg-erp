<?php

namespace Modules\Achats\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
        $purchase_order->load(['supplier', 'lines', 'requester', 'approver', 'approval.hierarchy.levels', 'approval.actions.approver']);

        return Inertia::render('Achats/PurchaseOrders/Show', [
            'purchaseOrder' => array_merge($purchase_order->toArray(), [
                'can_approve' => request()->user()?->can('approve', $purchase_order) ?? false,
            ]),
        ]);
    }

    public function edit(PurchaseOrder $purchase_order)
    {
        return Inertia::render('Achats/PurchaseOrders/Form', [
            'purchaseOrder' => $purchase_order,
        ]);
    }
}
