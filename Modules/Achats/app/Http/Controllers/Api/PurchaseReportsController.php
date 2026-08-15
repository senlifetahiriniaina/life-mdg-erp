<?php

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Achats\Models\PurchaseOrder;

/**
 * @group Controllers - Purchase Reports
 *
 * Manage Purchase Reports resources.
 */
class PurchaseReportsController extends Controller
{
    public function spending()
    {
        // Implementation to follow
        return [];
    }

    public function pendingReceipts()
    {
        $pos = PurchaseOrder::where('status', 'approved')
            ->whereDoesntHave('receipt')
            ->with('supplier')
            ->get();

        return $pos;
    }

    public function overdueInvoices()
    {
        // Implementation to follow
        return [];
    }
}
