<?php

declare(strict_types=1);

namespace Modules\Strategy\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategicAlert;
use Modules\Strategy\Services\BenchmarkService;
use Modules\Strategy\Services\CorrelationAnalysisService;
use Modules\Strategy\Services\OkrService;
use Modules\Strategy\Services\SignalEngineService;
use Modules\Strategy\Services\StrategyAIService;
use Modules\Strategy\Services\StrategyPlanService;
use Modules\Strategy\Services\StrategyRatioService;

class StrategyPageController extends Controller
{
    public function __construct(
        private readonly StrategyRatioService      $ratioService,
        private readonly StrategyPlanService       $planService,
        private readonly BenchmarkService          $benchmarkService,
        private readonly CorrelationAnalysisService $correlationService,
        private readonly OkrService                $okrService,
        private readonly SignalEngineService        $signalEngine,
        private readonly StrategyAIService         $aiService,
    ) {}

    /**
     * GET /strategy — main cockpit dashboard
     */
    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId($request);

        $ratios       = $this->ratioService->allRatiosWithStatus($tenantId);
        $activePlan   = StrategyPlan::forTenant($tenantId)->active()->with('pillars')->first();
        $alerts       = StrategicAlert::forTenant($tenantId)->active()->latest('triggered_at')->take(5)->get();
        $correlations = $this->correlationService->topCorrelations(5, true);
        $signals      = $this->signalEngine->analyze($tenantId);

        $okrTree = $activePlan
            ? $this->okrService->getOkrTree($tenantId, $activePlan->id)
            : [];

        $aiRecommendations = $this->aiService->recommend(
            array_merge(...array_values($ratios)),
            ['tenant_id' => $tenantId, 'plan_name' => $activePlan?->name],
            $request->user()->locale ?? 'fr',
        );

        return Inertia::render('Strategy/Index', [
            'ratios'            => $ratios,
            'activePlan'        => $activePlan,
            'okrTree'           => $okrTree,
            'alerts'            => $alerts,
            'correlations'      => $correlations,
            'signals'           => $signals,
            'aiRecommendations' => $aiRecommendations,
        ]);
    }

    /**
     * GET /strategy/plans — list all strategic plans
     */
    public function plans(Request $request): Response
    {
        $tenantId = $this->tenantId($request);

        $plans = StrategyPlan::forTenant($tenantId)
            ->with(['pillars', 'objectives'])
            ->latest()
            ->paginate(20);

        $plans->getCollection()->each(function (StrategyPlan $plan): void {
            $plan->health_score = $this->planService->computeHealthScore($plan);
        });

        return Inertia::render('Strategy/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    /**
     * GET /strategy/plans/{id} — plan detail with full OKR tree
     */
    public function planShow(Request $request, int $id): Response
    {
        $tenantId = $this->tenantId($request);

        $plan = StrategyPlan::forTenant($tenantId)
            ->with(['pillars', 'objectives.keyResults'])
            ->findOrFail($id);

        $tree        = $this->planService->getFullTree($id);
        $healthScore = $this->planService->computeHealthScore($plan);

        return Inertia::render('Strategy/Plans/Show', [
            'plan'        => $plan,
            'tree'        => $tree,
            'healthScore' => $healthScore,
        ]);
    }

    /**
     * GET /strategy/ratios — strategic KPI ratios with benchmarks
     */
    public function ratios(Request $request): Response
    {
        $tenantId = $this->tenantId($request);
        $country  = $request->input('country', 'WW');
        $industry = $request->input('industry', 'general');
        $module   = $request->input('module');

        $ratios = $module
            ? [$module => $this->ratioService->ratiosForModule($module, $tenantId)]
            : $this->ratioService->allRatiosWithStatus($tenantId);

        $modules = array_keys($this->ratioService->allRatiosWithStatus($tenantId));

        return Inertia::render('Strategy/Ratios/Index', [
            'ratios'          => $ratios,
            'modules'         => $modules,
            'selectedModule'  => $module,
            'filters'         => compact('country', 'industry'),
        ]);
    }

    /**
     * GET /strategy/benchmarks — industry benchmark comparison
     */
    public function benchmarks(Request $request): Response
    {
        $country  = $request->input('country');
        $industry = $request->input('industry');
        $year     = $request->integer('year') ?: null;

        $benchmarks = $this->benchmarkService->listAll($country, $industry, $year);

        return Inertia::render('Strategy/Benchmarks/Index', [
            'benchmarks' => $benchmarks,
            'filters'    => compact('country', 'industry', 'year'),
        ]);
    }

    /**
     * GET /strategy/correlations — KPI correlation matrix
     */
    public function correlations(Request $request): Response
    {
        $matrix       = $this->correlationService->matrixView();
        $topPositive  = $this->correlationService->topCorrelations(10, false);
        $topNegative  = $this->correlationService->topCorrelations(10, true);

        return Inertia::render('Strategy/Correlations/Index', [
            'matrix'      => $matrix,
            'topPositive' => $topPositive,
            'topNegative' => array_filter(
                $topNegative,
                fn(array $c) => ($c['coefficient'] ?? 0) < 0
            ),
        ]);
    }

    /**
     * GET /strategy/objectives — OKR objective management
     */
    public function objectives(Request $request): Response
    {
        $tenantId  = $this->tenantId($request);
        $planId    = $request->integer('plan_id');

        $plans = StrategyPlan::forTenant($tenantId)->active()->get(['id', 'name']);

        $okrTree = $planId
            ? $this->okrService->getOkrTree($tenantId, $planId)
            : ($plans->first() ? $this->okrService->getOkrTree($tenantId, $plans->first()->id) : []);

        return Inertia::render('Strategy/Objectives/Index', [
            'plans'          => $plans,
            'selectedPlanId' => $planId ?: $plans->first()?->id,
            'okrTree'        => $okrTree,
        ]);
    }

    /**
     * GET /strategy/sector-kpi — Chantier 26 (volet E). KPI sectoriels
     * textile/EPI calculés en direct sur les données réelles du volet A
     * (CostingSheet/ProductionOrder/SourcingBenchmark, Chantier 21/17) —
     * jamais de chiffre inventé. Method-injected rather than added to the
     * constructor to avoid touching every other action's dependency list.
     */
    public function sectorKpi(\Modules\Strategy\Services\TextileSectorKpiService $kpiService): Response
    {
        return Inertia::render('Strategy/SectorKpi/Index', [
            'margin'              => $kpiService->marginByFamily(),
            'cost_structure'      => $kpiService->costStructure(),
            'lead_time'           => $kpiService->subcontractingLeadTime(),
            'production_mix'      => $kpiService->productionMixByFamily(),
            'material_variance'   => $kpiService->materialPriceVariance(),
        ]);
    }

    /**
     * GET /strategy/cascade — OKR alignment cascade map.
     *
     * Modules/Strategy/resources/js/Pages/Cascade/Index.vue is a real,
     * complete, already-working page (self-fetches GET /api/v1/strategy/cascade)
     * that had zero web route anywhere — this is the quick-win wiring for it,
     * no new UI work needed. No server-side props: the page is self-fetching.
     */
    public function cascade(): Response
    {
        return Inertia::render('Strategy/Cascade/Index');
    }

    /**
     * Chantier 10 (Strategy): was $request->user()->tenant_id inlined at every
     * call site — the phantom users.tenant_id column, never populated for
     * real users, so every tenant's cockpit/plans/ratios/objectives pages
     * silently collapsed into one shared 'default' bucket (a live
     * cross-tenant leak, despite the Chantier 8.6 comment here claiming this
     * column was correct). Fixed to the real company_id boundary column,
     * matching StrategyPlanController::tenantId().
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }
}
