<?php

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Services\PurchaseOrderExportService;

/**
 * @group Controllers - Purchase Order Export
 *
 * Manage Purchase Order Export resources.
 */
class PurchaseOrderExportController extends Controller
{
    public function __construct(protected PurchaseOrderExportService $exportService) {}

    /**
     * Export PO as JSON
     */
    public function exportJson(PurchaseOrder $purchase_order)
    {
        $json = $this->exportService->exportToJson($purchase_order);

        return response($json)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', "attachment; filename=\"PO-{$purchase_order->po_number}.json\"");
    }

    /**
     * Export PO as CSV
     */
    public function exportCsv(PurchaseOrder $purchase_order)
    {
        $csv = $this->exportService->exportToCsv($purchase_order);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"PO-{$purchase_order->po_number}.csv\"");
    }

    /**
     * Get PO summary data
     */
    public function summary(PurchaseOrder $purchase_order)
    {
        return response()->json($this->exportService->getPoSummary($purchase_order));
    }
}
