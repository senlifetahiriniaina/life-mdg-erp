<?php

namespace Modules\Achats\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Achats\Models\PurchaseReceipt;

/**
 * Chantier 10: PurchaseReceipts/{Index,Form,Show}.vue are real,
 * fully-built, self-fetching pages (all 3 already call the real
 * /api/v1/achats/purchase-receipts* endpoints directly via fetch()) with
 * zero web route anywhere in the module — a 404 on every URL. Matches the
 * existing PurchaseOrders/RFQs/Suppliers Web controller pattern; no server
 * props are needed since every page self-fetches its own data.
 */
class PurchaseReceiptController extends Controller
{
    public function index()
    {
        return Inertia::render('Achats/PurchaseReceipts/Index');
    }

    public function create()
    {
        return Inertia::render('Achats/PurchaseReceipts/Form');
    }

    public function show(PurchaseReceipt $purchase_receipt)
    {
        return Inertia::render('Achats/PurchaseReceipts/Show');
    }

    public function edit(PurchaseReceipt $purchase_receipt)
    {
        return Inertia::render('Achats/PurchaseReceipts/Form');
    }
}
