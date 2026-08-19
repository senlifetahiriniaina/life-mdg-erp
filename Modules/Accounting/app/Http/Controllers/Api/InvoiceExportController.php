<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Accounting\Exports\InvoicesExport;
use Modules\Accounting\Models\Invoice;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Accounting - Invoice Exports
 *
 * Export invoices as PDF or Excel.
 */
class InvoiceExportController extends Controller
{
    /**
     * Download a single invoice as PDF.
     *
     * @urlParam invoice integer required The invoice ID. Example: 1
     *
     * @response 200 Binary PDF file (Content-Type: application/pdf)
     */
    public function pdf(Invoice $invoice): Response
    {
        // Chantier 19 re-verification: Invoice has no `lines()` relation at all (only
        // `lineItems()`) — a guaranteed "Call to undefined relationship [lines]" fatal
        // on every PDF download, confirmed empirically. This endpoint is linked directly
        // from the real, routed Invoices/Index.vue page (the PDF icon on every row).
        $invoice->load(['lineItems', 'createdBy', 'journal']);

        $pdf = Pdf::loadView('accounting.invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');

        $filename = 'invoice-'.($invoice->number ?? $invoice->id).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export all invoices matching current filters as Excel (XLSX).
     *
     * @queryParam status string Filter by status (draft|open|paid|overdue). Example: paid
     * @queryParam from string Filter from date (Y-m-d). Example: 2026-01-01
     * @queryParam to string Filter to date (Y-m-d). Example: 2026-12-31
     *
     * @response 200 Binary XLSX file (Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet)
     */
    public function excel(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['status', 'from', 'to', 'type']);

        return Excel::download(new InvoicesExport($filters), 'invoices.xlsx');
    }
}
