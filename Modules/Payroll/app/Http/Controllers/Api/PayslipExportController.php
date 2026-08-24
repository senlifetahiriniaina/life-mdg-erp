<?php

declare(strict_types=1);

namespace Modules\Payroll\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Modules\Payroll\Models\Payslip;

/**
 * @group Payroll — Payslip Export
 *
 * Chantier 32.18 (Payroll deep audit — layer 14c, report generation):
 * Chantier 29's report-proposal pass named "bulletin de paie PDF" (payslip
 * PDF export) as the single most naturally-expected-but-missing PDF export
 * in the whole app — the underlying payslip generation was already real
 * (PayrollIntegrationService::generatePayslip()), but nothing ever turned
 * one into a document an employee could actually download. Built following
 * the same Barryvdh\DomPDF pattern already established by Accounting's
 * InvoiceExportController — a root-level Blade view (resources/views/
 * payroll/payslip/pdf.blade.php), not one under Modules/Payroll/resources
 * (matching every other real PDF export in this app: accounting.*,
 * analytics.*, strategy.*).
 */
class PayslipExportController extends Controller
{
    /**
     * Download a single payslip as a PDF ("bulletin de paie").
     *
     * Reuses PayrollPolicy::view() (already fixed for the cross-tenant IDOR
     * this same chantier found) — payroll staff of the payslip's own
     * company, or the employee it belongs to.
     *
     * @urlParam payslip integer required The payslip ID. Example: 1
     *
     * @response 200 Binary PDF file (Content-Type: application/pdf)
     */
    public function pdf(Payslip $payslip): Response
    {
        $this->authorize('view', $payslip);

        $payslip->loadMissing('employee');

        $filename = 'bulletin-paie-'.$payslip->id.'-'.($payslip->period?->format('Y-m') ?? 'periode').'.pdf';

        $pdf = Pdf::loadView('payroll.payslip.pdf', ['payslip' => $payslip])
            ->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
