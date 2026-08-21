<?php

declare(strict_types=1);

namespace Modules\Strategy\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Strategy\Exports\StrategyExecutiveReportExport;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Services\CorrelationAnalysisService;
use Modules\Strategy\Services\OkrService;
use Modules\Strategy\Services\StrategyPlanService;
use Modules\Strategy\Services\StrategyRatioService;
use Modules\Strategy\Services\TextileSectorKpiService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Chantier 29 — "Rapport de pilotage stratégique" (executive strategy
 * report), PDF + Excel. This is the one module in the whole app with no
 * export capability at all (confirmed via an exhaustive grep across
 * Modules/Strategy for every export pattern used elsewhere in this repo —
 * zero matches), despite being explicitly the module built for exactly the
 * leadership/strategic-piloting audience ("Strategy First").
 *
 * Deliberately presents already-real, already-computed data in document
 * form — no new business logic, no new calculations. Every section is
 * pulled by calling the SAME services the on-screen cockpit
 * (StrategyPageController::index()) already calls, so the report and the
 * live dashboard can never structurally diverge. Same PDF/Excel pattern as
 * Chantier 26 (volet A)'s CashflowForecastExportController: DomPDF against
 * a root-level (not Modules/) Blade view, Maatwebsite\Excel against an
 * Exports class.
 */
class StrategyReportExportController extends Controller
{
    public function __construct(
        private readonly StrategyRatioService $ratioService,
        private readonly StrategyPlanService $planService,
        private readonly CorrelationAnalysisService $correlationService,
        private readonly OkrService $okrService,
        private readonly TextileSectorKpiService $sectorKpiService,
    ) {}

    /**
     * GET /api/v1/strategy/executive-report/export/pdf
     */
    public function pdf(Request $request): Response
    {
        $data = $this->gatherReportData($request);

        $pdf = Pdf::loadView('strategy.executive-report.pdf', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download('rapport-pilotage-strategique-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * GET /api/v1/strategy/executive-report/export/excel
     */
    public function excel(Request $request): BinaryFileResponse
    {
        $data = $this->gatherReportData($request);

        return Excel::download(
            new StrategyExecutiveReportExport($data),
            'rapport-pilotage-strategique-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * Gathers every report section by calling the same real services the
     * on-screen cockpit already uses — no query logic is duplicated here.
     *
     * @return array<string, mixed>
     */
    private function gatherReportData(Request $request): array
    {
        $tenantId = $this->tenantId($request);
        $user     = $request->user();

        // Ratios — same flatten-by-module shape RatioController::index() already returns.
        $ratiosByModule = $this->ratioService->allRatiosWithStatus($tenantId);
        $ratios = [];
        foreach ($ratiosByModule as $module => $moduleRatios) {
            foreach ($moduleRatios as $ratio) {
                $ratios[] = array_merge($ratio, ['module' => $module]);
            }
        }

        // Plans — same query/health-score computation as StrategyPageController::plans().
        $plans = StrategyPlan::forTenant($tenantId)
            ->withCount('objectives')
            ->orderBy('created_at', 'desc')
            ->get();
        $plans->each(function (StrategyPlan $plan): void {
            $plan->health_score = $this->planService->computeHealthScore($plan);
        });

        // Correlations — same call as StrategyPageController::index()/correlations().
        $correlations = $this->correlationService->topCorrelations(10, true);

        // OKR tree for the active plan — same call as StrategyPageController::index().
        $okrTree = $this->okrService->getOkrTree($tenantId);

        // Sector KPI cockpit (Chantier 26 volet E) — same calls as
        // StrategyPageController::sectorKpi(). Note: CostingSheet/ProductionOrder
        // have no tenant/company column at all (documented gap, Chantier 19) —
        // this section inherits the same unscoped-by-tenant scope the rest of
        // Inventory/the live sector-kpi page already has, not a regression
        // introduced here.
        $sector = [
            'margin'            => $this->sectorKpiService->marginByFamily(),
            'cost_structure'    => $this->sectorKpiService->costStructure(),
            'lead_time'         => $this->sectorKpiService->subcontractingLeadTime(),
            'production_mix'    => $this->sectorKpiService->productionMixByFamily(),
            'material_variance' => $this->sectorKpiService->materialPriceVariance(),
        ];

        return [
            'company_name' => $user?->company?->name ?? config('app.name'),
            // Numeric mm/yyyy rather than a translated month name — avoids a
            // dependency on config('app.locale') actually being 'fr' (this
            // app defaults APP_LOCALE to 'en' unless overridden), so the
            // period label is stable regardless of environment.
            'period_label' => now()->format('m/Y'),
            'generated_at' => now(),
            'ratios'       => $ratios,
            'plans'        => $plans,
            'correlations' => $correlations,
            'okr'          => $okrTree,
            'sector'       => $sector,
        ];
    }

    /**
     * Same company_id-based tenant boundary already established throughout
     * this module (see StrategyPlanController::tenantId()'s docblock for the
     * full rationale) — never the phantom users.tenant_id column.
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }
}
