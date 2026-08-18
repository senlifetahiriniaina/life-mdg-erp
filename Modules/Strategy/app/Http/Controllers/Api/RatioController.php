<?php

declare(strict_types=1);

namespace Modules\Strategy\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Strategy\Services\StrategyRatioService;
use Modules\Strategy\Services\BenchmarkService;
use Modules\Strategy\Services\CorrelationAnalysisService;
use Modules\Strategy\Services\StrategyAIService;
use Modules\Strategy\Models\StrategicAlert;

class RatioController extends Controller
{
    public function __construct(
        private readonly StrategyRatioService       $ratioService,
        private readonly BenchmarkService           $benchmarkService,
        private readonly CorrelationAnalysisService $correlationService,
        private readonly StrategyAIService          $aiService,
    ) {}

    /**
     * GET /api/v1/strategy/ratios
     * All ratios with current + benchmark values.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $all      = $this->ratioService->allRatiosWithStatus($tenantId);

        // Flatten to array with module key
        $flat = [];
        foreach ($all as $module => $ratios) {
            foreach ($ratios as $ratio) {
                $flat[] = array_merge($ratio, ['module' => $module]);
            }
        }

        return response()->json([
            'data' => $flat,
            'meta' => [
                'total'   => count($flat),
                'modules' => array_keys($all),
            ],
        ]);
    }

    /**
     * GET /api/v1/strategy/ratios/{module}
     * Module-specific ratios.
     */
    public function byModule(Request $request, string $module): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $ratios   = $this->ratioService->ratiosForModule(ucfirst($module), $tenantId);

        return response()->json([
            'data'   => $ratios,
            'module' => $module,
        ]);
    }

    /**
     * GET /api/v1/strategy/benchmarks
     * Industry benchmark data.
     */
    public function benchmarks(Request $request): JsonResponse
    {
        $data = $this->benchmarkService->listAll(
            $request->query('country'),
            $request->query('industry'),
            $request->query('year') ? (int) $request->query('year') : null,
        );

        return response()->json([
            'data'    => $data,
            'filters' => [
                'country'  => $request->query('country'),
                'industry' => $request->query('industry'),
                'year'     => $request->query('year'),
            ],
        ]);
    }

    /**
     * GET /api/v1/strategy/correlations
     * Top KPI correlations.
     */
    public function correlations(Request $request): JsonResponse
    {
        $limit  = (int) ($request->query('limit', 10));
        $matrix = $this->correlationService->matrixView();

        return response()->json([
            'data' => $matrix,
            'meta' => ['limit' => $limit],
        ]);
    }

    /**
     * POST /api/v1/strategy/ai/recommend
     * Get AI strategic recommendations from current ratios.
     */
    public function aiRecommend(Request $request): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $locale    = $request->input('locale', 'fr');
        $context   = $request->input('context', []);

        $ratioData     = $this->ratioService->allRatiosWithStatus($tenantId);
        $recommendations = $this->aiService->recommend($ratioData, $context, $locale);

        return response()->json($recommendations);
    }

    /**
     * GET /api/v1/strategy/alerts
     * Active strategic alerts.
     */
    public function alerts(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $alerts = StrategicAlert::forTenant($tenantId)
            ->active()
            ->orderBy('severity')
            ->orderBy('triggered_at', 'desc')
            ->limit(50)
            ->get();

        // If no DB alerts, generate from ratio analysis
        if ($alerts->isEmpty()) {
            $alerts = $this->generateAlertsMock($tenantId);
        }

        return response()->json(['data' => $alerts]);
    }

    private function generateAlertsMock(string $tenantId): array
    {
        $ratios = $this->ratioService->allRatiosWithStatus($tenantId);
        $alerts = [];
        $id     = 1;

        foreach ($ratios as $module => $moduleRatios) {
            foreach ($moduleRatios as $ratio) {
                if (($ratio['status'] ?? 'green') === 'red') {
                    $alerts[] = [
                        'id'           => $id++,
                        'tenant_id'    => $tenantId,
                        'type'         => 'ratio_below_target',
                        'severity'     => 'warning',
                        'message'      => "{$ratio['name']} ({$module}) est en dessous du seuil cible: {$ratio['current_value']} {$ratio['unit']}",
                        'module'       => $module,
                        'triggered_at' => now()->toISOString(),
                        'resolved_at'  => null,
                    ];
                }
            }
        }

        return $alerts;
    }

    /**
     * Chantier 8.6 (Strategy): already used $request->user()?->tenant_id (the
     * correct, non-client-controlled column) before this pass — extracted into
     * the same private helper as the other Strategy controllers for
     * consistency. See StrategyPlanController::tenantId() for the full
     * rationale on why this column (not X-Tenant-Id) is the right source.
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->tenant_id ?? 'default');
    }
}
