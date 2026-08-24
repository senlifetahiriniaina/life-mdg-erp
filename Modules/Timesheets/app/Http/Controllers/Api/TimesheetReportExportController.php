<?php

declare(strict_types=1);

namespace Modules\Timesheets\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Timesheets\Exports\ProjectBillingReportExport;
use Modules\Timesheets\Services\ProjectBillingService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Chantier 32.19 (Timesheets deep 14-layer audit, layer 14c — proposed
 * report). Real PDF/Excel export of the project-billing report — the exact
 * report Chantier 29's own report-proposal catalogue named for this module
 * ("rapport de facturation/heures par projet"), using data that was already
 * real (ProjectBillingService::getProjectBillingReportData(), extracted
 * from the already-live GET /api/v1/timesheets/reports/project-billing
 * endpoint so both share the exact same aggregation).
 *
 * @group Timesheets - Report Exports
 */
class TimesheetReportExportController extends Controller
{
    public function __construct(private readonly ProjectBillingService $billingService) {}

    /**
     * GET /api/v1/timesheets/reports/project-billing/export/pdf
     */
    public function pdf(Request $request): Response
    {
        $data = $this->reportData($request);

        $pdf = Pdf::loadView('timesheets.reports.project-billing', ['data' => $data])
            ->setPaper('a4', 'landscape');

        return $pdf->download('rapport-facturation-projets-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * GET /api/v1/timesheets/reports/project-billing/export/excel
     */
    public function excel(Request $request): BinaryFileResponse
    {
        $data = $this->reportData($request);

        return Excel::download(
            new ProjectBillingReportExport($data),
            'rapport-facturation-projets-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** @return array<string, mixed> */
    private function reportData(Request $request): array
    {
        $from = $request->query('from_date', now()->subDays(30)->format('Y-m-d'));
        $to   = $request->query('to_date', now()->format('Y-m-d'));

        return $this->billingService->getProjectBillingReportData(
            $from,
            $to,
            $request->filled('project_id') ? (int) $request->project_id : null,
        );
    }
}
