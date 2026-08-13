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
        $tenantId = (string) ($request->user()->tenant_id ?? 'default');

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
        $tenantId = (string) ($request->user()->tenant_id ?? 'default');

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
        $tenantId = (string) ($request->user()->tenant_id ?? 'default');

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
        $tenantId = (string) ($request->user()->tenant_id ?? 'default');
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
        $tenantId  = (string) ($request->user()->tenant_id ?? 'default');
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
}
