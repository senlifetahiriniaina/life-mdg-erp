<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Projects - Reports
 */
class ProjectReportController extends Controller
{
    public function __construct(private readonly ProjectReportService $reportService) {}

    /**
     * Get report data as JSON.
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $data = $this->reportService->generateStatusReport($project);

        return response()->json($data);
    }

    /**
     * Download PDF report.
     */
    public function pdf(Project $project): StreamedResponse
    {
        $this->authorize('view', $project);

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
     * Return rendered HTML report (for preview).
     */
    public function html(Project $project): Response
    {
        $this->authorize('view', $project);

        $html = $this->reportService->renderReportHtml($project);

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
