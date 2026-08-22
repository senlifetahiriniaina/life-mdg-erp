<?php

namespace Modules\Analytics\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Analytics\Models\ForecastAlert;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Models\ForecastScenario;
use Modules\Analytics\Services\Forecasting\CashflowForecastService;
use Modules\Analytics\Services\Forecasting\DemandForecastService;
use Modules\Analytics\Services\Forecasting\HrForecastService;
use Modules\Analytics\Services\Forecasting\ProductionForecastService;
use Modules\Analytics\Services\ForecastingEngineService;

/**
 * Contrôleur API — Moteur de prévision IA (Phase 41)
 *
 * Toutes les routes requièrent auth:sanctum.
 * Le tenant_id est résolu depuis l'utilisateur authentifié (company_id).
 */
class ForecastingController extends Controller
{
    public function __construct(
        private readonly ForecastingEngineService  $engine,
        private readonly DemandForecastService     $demandService,
        private readonly CashflowForecastService   $cashflowService,
        private readonly HrForecastService         $hrService,
        private readonly ProductionForecastService $productionService,
    ) {}

    // ─── Modèles de prévision ─────────────────────────────────────

    /**
     * GET /api/v1/forecasting/models
     */
    public function indexModels(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $models   = ForecastModel::forTenant($tenantId)
            ->when($request->module, fn ($q) => $q->where('module', $request->module))
            ->when($request->active !== null, fn ($q) => $q->where('is_active', (bool) $request->active))
            ->withCount('predictions', 'alerts')
            ->orderBy('name')
            ->paginate(20);

        return response()->json($models);
    }

    /**
     * POST /api/v1/forecasting/models
     */
    public function storeModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'module'       => 'required|in:demand,cashflow,hr,production,revenue,inventory',
            'entity_type'  => 'nullable|in:product,category,employee,account',
            'entity_id'    => 'nullable|integer',
            'algorithm'    => 'required|in:linear_regression,moving_average,exponential_smoothing,ai_claude',
            'horizon_days' => 'required|in:30,60,90,180,365',
            'config'       => 'nullable|array',
        ]);

        $model = ForecastModel::create(array_merge($validated, [
            'tenant_id' => $this->tenantId($request),
            'is_active' => true,
        ]));

        return response()->json($model, 201);
    }

    /**
     * GET /api/v1/forecasting/models/{id}
     */
    public function showModel(Request $request, int $id): JsonResponse
    {
        $model = $this->findModel($id, $request);

        $model->load([
            'predictions' => fn ($q) => $q->where('forecast_date', '>=', now()->toDateString())
                                          ->orderBy('forecast_date')
                                          ->limit(90),
            'alerts' => fn ($q) => $q->active()->orderByDesc('created_at')->limit(10),
        ]);

        return response()->json($model);
    }

    /**
     * PUT /api/v1/forecasting/models/{id}
     */
    public function updateModel(Request $request, int $id): JsonResponse
    {
        $model     = $this->findModel($id, $request);
        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'algorithm'    => 'sometimes|in:linear_regression,moving_average,exponential_smoothing,ai_claude',
            'horizon_days' => 'sometimes|in:30,60,90,180,365',
            'is_active'    => 'sometimes|boolean',
            'config'       => 'sometimes|array',
        ]);

        $model->update($validated);

        return response()->json($model->fresh());
    }

    /**
     * POST /api/v1/forecasting/models/{id}/train
     */
    public function trainModel(Request $request, int $id): JsonResponse
    {
        $model = $this->findModel($id, $request);
        $model = $this->engine->train($model);

        return response()->json([
            'message'          => 'Modèle entraîné avec succès.',
            'confidence_level' => $model->confidence_level,
            'last_trained_at'  => $model->last_trained_at,
            'next_retrain_at'  => $model->next_retrain_at,
        ]);
    }

    /**
     * GET /api/v1/forecasting/models/{id}/predictions
     */
    public function modelPredictions(Request $request, int $id): JsonResponse
    {
        $model = $this->findModel($id, $request);

        $predictions = $model->predictions()
            ->when(
                $request->from,
                fn ($q) => $q->where('forecast_date', '>=', $request->from)
            )
            ->when(
                $request->to,
                fn ($q) => $q->where('forecast_date', '<=', $request->to)
            )
            ->orderBy('forecast_date')
            ->get();

        return response()->json([
            'model'       => $model->only('id', 'name', 'module', 'algorithm', 'confidence_level'),
            'predictions' => $predictions,
        ]);
    }

    // ─── Alertes ──────────────────────────────────────────────────

    /**
     * GET /api/v1/forecasting/alerts
     */
    public function indexAlerts(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $alerts   = ForecastAlert::forTenant($tenantId)
            ->when(! $request->boolean('include_acknowledged'), fn ($q) => $q->active())
            ->when($request->severity, fn ($q) => $q->where('severity', $request->severity))
            ->with('forecastModel:id,name,module')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($alerts);
    }

    /**
     * POST /api/v1/forecasting/alerts/{id}/acknowledge
     */
    public function acknowledgeAlert(Request $request, int $id): JsonResponse
    {
        $alert = ForecastAlert::forTenant($this->tenantId($request))->findOrFail($id);
        $alert->acknowledge($request->user()->id);

        return response()->json(['message' => 'Alerte acquittée.', 'alert' => $alert->fresh()]);
    }

    // ─── Scénarios ────────────────────────────────────────────────

    /**
     * GET /api/v1/forecasting/scenarios
     */
    public function indexScenarios(Request $request): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $scenarios = ForecastScenario::forTenant($tenantId)
            ->with('forecastModel:id,name,module')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($scenarios);
    }

    /**
     * POST /api/v1/forecasting/scenarios
     */
    public function storeScenario(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_id'    => 'required|integer',
            'name'        => 'required|string|max:255',
            'assumptions' => 'required|array',
        ]);

        $scenario = $this->engine->createScenario(
            $validated['model_id'],
            $validated['name'],
            $validated['assumptions'],
            $this->tenantId($request)
        );

        return response()->json($scenario, 201);
    }

    /**
     * GET /api/v1/forecasting/scenarios/compare
     * Query: ?ids[]=1&ids[]=2&ids[]=3
     */
    public function compareScenarios(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (count($ids) < 2) {
            return response()->json(['error' => 'Au moins 2 scénarios requis pour la comparaison.'], 422);
        }

        return response()->json($this->engine->compareScenarios(array_map('intval', $ids), $this->tenantId($request)));
    }

    // ─── Prévisions spécialisées ──────────────────────────────────

    /**
     * GET /api/v1/forecasting/demand/{productId}
     */
    public function demandForecast(Request $request, int $productId): JsonResponse
    {
        $days   = (int) $request->input('days', 90);
        $result = $this->demandService->forecastProduct($productId, $this->tenantId($request), $days);

        return response()->json($result);
    }

    /**
     * GET /api/v1/forecasting/cashflow
     * Query: ?days=30|60|90|180 (défaut 90)
     */
    public function cashflowForecast(Request $request): JsonResponse
    {
        $days   = (int) $request->input('days', 90);
        $days   = in_array($days, [30, 60, 90, 180], true) ? $days : 90;
        $result = $this->cashflowService->forecast90Days($this->tenantId($request), $days);

        return response()->json($result);
    }

    /**
     * GET /api/v1/forecasting/hr/headcount
     */
    public function hrHeadcountForecast(Request $request): JsonResponse
    {
        $months = (int) $request->input('months', 6);
        $result = $this->hrService->forecastHeadcount($this->tenantId($request), $months);

        return response()->json(['headcount_forecast' => $result]);
    }

    /**
     * GET /api/v1/forecasting/hr/turnover-risk
     */
    public function turnoverRisk(Request $request): JsonResponse
    {
        $result = $this->hrService->predictTurnoverRisk($this->tenantId($request));

        return response()->json([
            'total_employees' => count($result),
            'high_risk'       => count(array_filter($result, fn ($r) => $r['risk_level'] === 'élevé')),
            'medium_risk'     => count(array_filter($result, fn ($r) => $r['risk_level'] === 'moyen')),
            'employees'       => $result,
        ]);
    }

    /**
     * GET /api/v1/forecasting/production
     */
    public function productionForecast(Request $request): JsonResponse
    {
        $days   = (int) $request->input('days', 90);
        $result = $this->productionService->forecastProductionNeeds($this->tenantId($request), $days);

        return response()->json($result);
    }

    /**
     * POST /api/v1/forecasting/ai/analyze
     * Corps : {module, data, context, locale}
     */
    public function aiAnalyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module'  => 'required|string',
            'data'    => 'required|array',
            'context' => 'nullable|string',
            'locale'  => 'nullable|string|size:2',
        ]);

        $result = $this->engine->aiforecast(
            $validated['data'],
            $validated['module'],
            $validated['context'] ?? '',
            $validated['locale'] ?? 'fr'
        );

        return response()->json($result);
    }

    /**
     * POST /api/v1/forecasting/ai/narrative
     * Génère une narrative IA enrichie pour des prévisions déjà calculées.
     */
    public function aiNarrative(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module'          => 'required|in:demand,cashflow,hr,production',
            'predictions'     => 'required|array',
            'historical_data' => 'nullable|array',
            'context'         => 'nullable|string',
            'locale'          => 'nullable|string|size:2',
        ]);

        $narrativeService = app(\Modules\Analytics\Services\AiForecastNarrativeService::class);
        $result = $narrativeService->generateNarrative(
            $validated['module'],
            $validated['predictions'],
            $validated['historical_data'] ?? [],
            $validated['locale'] ?? 'fr',
            $validated['context'] ?? '',
        );

        return response()->json($result);
    }

    /**
     * GET /api/v1/forecasting/hub
     * Résumé agrégé : 4 modules + comptage des alertes actives.
     */
    public function hub(Request $request): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $alertCount = \Modules\Analytics\Models\ForecastAlert::forTenant($tenantId)
            ->active()
            ->count();

        $criticalCount = \Modules\Analytics\Models\ForecastAlert::forTenant($tenantId)
            ->active()
            ->critical()
            ->count();

        $models = ForecastModel::forTenant($tenantId)
            ->active()
            ->select('id', 'name', 'module', 'algorithm', 'confidence_level', 'last_trained_at')
            ->get()
            ->groupBy('module');

        return response()->json([
            'tenant_id'      => $tenantId,
            'alert_count'    => $alertCount,
            'critical_alerts' => $criticalCount,
            'modules'        => [
                'demand'     => $models->get('demand', collect())->first(),
                'cashflow'   => $models->get('cashflow', collect())->first(),
                'hr'         => $models->get('hr', collect())->first(),
                'production' => $models->get('production', collect())->first(),
            ],
        ]);
    }

    /**
     * GET /api/v1/forecasting/demand
     * Prévision de la demande par paramètre (product_id ou category).
     */
    public function demandForecastByParam(Request $request): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $productId  = (int) $request->input('product_id', 0);
        $category   = $request->input('category', '');
        $days       = (int) $request->input('days', 90);

        if ($productId > 0) {
            $result = $this->demandService->forecastProduct($productId, $tenantId, $days);
        } elseif ($category) {
            $result = $this->demandService->forecastCategory($category, $tenantId, $days);
        } else {
            return response()->json(['error' => 'Paramètre product_id ou category requis.'], 422);
        }

        return response()->json($result);
    }

    /**
     * GET /api/v1/forecasting/hr
     * Hub RH : headcount + turnover + congés + coût masse salariale.
     */
    public function hrForecast(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $months   = (int) $request->input('months', 6);

        return response()->json([
            'headcount'      => $this->hrService->forecastHeadcount($tenantId, $months),
            'payroll_cost'   => $this->hrService->forecastPayrollCost($tenantId, $months),
            'leave_demand'   => $this->hrService->forecastLeaveDemand($tenantId),
        ]);
    }

    // ─── Utilitaires ──────────────────────────────────────────────

    /**
     * Chantier 19 (Lot 5): confirmed empirically that this fell back to the
     * acting user's own `id` whenever `company_id` was null — the exact
     * "private per-user bucket" bug already documented and fixed for AI's
     * AiUsageBudgetService and API's RequestLogController/WebhookController
     * (Chantier 19 Lot 3): not a cross-tenant leak (no shared/guessable
     * fallback), but two colleagues at the same real company with no
     * `company_id` set each silently got their own forecast models/alerts/
     * scenarios instead of sharing one company bucket. Fixed to the
     * app-wide `?? 0` convention used everywhere else this session.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()->company_id ?? 0);
    }

    private function findModel(int $id, Request $request): ForecastModel
    {
        return ForecastModel::forTenant($this->tenantId($request))->findOrFail($id);
    }
}
