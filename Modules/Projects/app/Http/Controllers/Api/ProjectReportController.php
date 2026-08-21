<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Projects\Exports\ProjectReportExport;
use Modules\Projects\Http\Controllers\Api\Concerns\ScopesToProjectCompany;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Projects - Reports
 */
class ProjectReportController extends Controller
{
    use ScopesToProjectCompany;

    public function __construct(private readonly ProjectReportService $reportService) {}

    /**
     * Get report data as JSON.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $this->assertSameCompanyAsProject($request, $project);

        $data = $this->reportService->generateStatusReport($project);

        return response()->json($data);
    }

    /**
     * Download PDF report.
     */
    public function pdf(Request $request, Project $project): StreamedResponse
    {
        $this->authorize('view', $project);
        $this->assertSameCompanyAsProject($request, $project);

        $html = $this->reportService->renderReportHtml($project);

        $pdf = Pdf::loadHTML($html);
        $filename = 'project-report-'.$project->id.'-'.now()->format('Y-m-d').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Download Excel report (multi-sheet: Tâches / Heures équipe / Jalons).
     *
     * Chantier 32.17 (14-layer deep audit, layer 14): the PDF export
     * already existed for real (Chantier 8.4); this closes the "no Excel
     * equivalent" gap Chantier 29's cross-module export audit documented.
     */
    public function excel(Request $request, Project $project): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $project);
        $this->assertSameCompanyAsProject($request, $project);

        $data = $this->reportService->generateStatusReport($project);
        $taskRows = $this->reportService->taskRows($project);
        $filename = 'project-report-'.$project->id.'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ProjectReportExport($data, $taskRows), $filename);
    }

    /**
     * Return rendered HTML report (for preview).
     */
    public function html(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);
        $this->assertSameCompanyAsProject($request, $project);

        $html = $this->reportService->renderReportHtml($project);

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
