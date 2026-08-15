<?php

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Services\PurchaseReceiptService;

/**
 * @group Controllers - Purchase Receipt
 *
 * Manage Purchase Receipt resources.
 */
class PurchaseReceiptController extends Controller
{
    public function __construct(protected PurchaseReceiptService $service) {}

    public function index()
    {
        // Implementation to follow
    }

    public function show(PurchaseReceipt $purchase_receipt)
    {
        return $purchase_receipt->load('lines');
    }

    public function store(PurchaseOrder $purchase_order)
    {
        // Implementation to follow
    }

    public function complete(PurchaseReceipt $purchase_receipt)
    {
        $this->service->completeReceipt($purchase_receipt);

        return $purchase_receipt->refresh();
    }

    public function recordQualityIssue(PurchaseReceipt $purchase_receipt)
    {
        // Implementation to follow
    }
}
