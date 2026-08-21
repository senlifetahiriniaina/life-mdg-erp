<?php

declare(strict_types=1);

namespace Modules\Analytics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Analytics\Exports\CashflowForecastExport;
use Modules\Analytics\Services\Forecasting\CashflowForecastService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Chantier 26 (volet A) — export PDF/Excel de la prévision de trésorerie.
 */
class CashflowForecastExportController extends Controller
{
    public function __construct(private readonly CashflowForecastService $cashflowService) {}

    /**
     * GET /api/v1/forecasting/cashflow/export/pdf?days=90
     */
    public function pdf(Request $request): Response
    {
        $days   = (int) $request->input('days', 90);
        $days   = in_array($days, [30, 60, 90, 180], true) ? $days : 90;
        $result = $this->cashflowService->forecast90Days($this->tenantId($request), $days);

        $pdf = Pdf::loadView('analytics.cashflow-forecast.pdf', ['result' => $result])
            ->setPaper('a4', 'portrait');

        return $pdf->download('previsions-tresorerie-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * GET /api/v1/forecasting/cashflow/export/excel?days=90
     */
    public function excel(Request $request): BinaryFileResponse
    {
        $days   = (int) $request->input('days', 90);
        $days   = in_array($days, [30, 60, 90, 180], true) ? $days : 90;
        $result = $this->cashflowService->forecast90Days($this->tenantId($request), $days);

        return Excel::download(
            new CashflowForecastExport($result['daily'], $result['summary']['currency']),
            'previsions-tresorerie-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->company_id ?? 0);
    }
}
