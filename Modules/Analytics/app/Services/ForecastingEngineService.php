<?php

namespace Modules\Analytics\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Analytics\Models\ForecastAlert;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Models\ForecastPrediction;
use Modules\Analytics\Models\ForecastScenario;

/**
 * Moteur de prévision IA — Phase 41
 *
 * Algorithmes disponibles :
 *   • moving_average         — Moyenne mobile simple (n périodes)
 *   • exponential_smoothing  — Lissage exponentiel de Holt-Winters (niveau + tendance + saisonnalité)
 *   • linear_regression      — Régression linéaire par moindres carrés avec IC à 95 %
 *   • ai_claude              — Prévision narrative via Claude (claude-sonnet-4-6)
 *
 * Africa First : gestion XOF/XAF, saisonnalité Ramadan, rentrée scolaire.
 */
class ForecastingEngineService
{
    // ─── Data Collection ──────────────────────────────────────────

    /**
     * Collecte les données historiques pour un module / entité donnés.
     *
     * @return array<int, array{date: string, value: float}>
     */
    public function collectHistoricalData(
        string $module,
        string $entityType,
        int $entityId,
        int $tenantId
    ): array {
        return match ($module) {
            'demand'     => $this->collectDemandData($entityType, $entityId, $tenantId),
            'cashflow'   => $this->collectCashflowData($tenantId),
            'hr'         => $this->collectHrData($entityType, $entityId, $tenantId),
            'production' => $this->collectProductionData($tenantId),
            'revenue'    => $this->collectRevenueData($tenantId),
            'inventory'  => $this->collectInventoryData($entityId, $tenantId),
            default      => [],
        };
    }

    private function collectDemandData(string $entityType, int $entityId, int $tenantId): array
    {
        // Requête sur sales_order_lines groupée par date
        $rows = DB::table('sales_order_lines as sol')
            ->join('sales_orders as so', 'so.id', '=', 'sol.order_id')
            ->selectRaw('DATE(so.ordered_at) as date, SUM(sol.quantity) as value')
            ->where('so.tenant_id', $tenantId)
            ->when($entityType === 'product', fn ($q) => $q->where('sol.product_id', $entityId))
            ->where('so.ordered_at', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value])->toArray();
    }

    private function collectCashflowData(int $tenantId): array
    {
        $rows = DB::table('accounting_transactions')
            ->selectRaw('DATE(transaction_date) as date, SUM(CASE WHEN type = \'income\' THEN amount ELSE -amount END) as value')
            ->where('tenant_id', $tenantId)
            ->where('transaction_date', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value])->toArray();
    }

    private function collectHrData(string $entityType, int $entityId, int $tenantId): array
    {
        $rows = DB::table('employees')
            ->selectRaw('DATE_FORMAT(hire_date, \'%Y-%m-01\') as date, COUNT(*) as value')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value])->toArray();
    }

    private function collectProductionData(int $tenantId): array
    {
        $rows = DB::table('manufacturing_orders')
            ->selectRaw('DATE(completed_at) as date, COUNT(*) as value')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value])->toArray();
    }

    private function collectRevenueData(int $tenantId): array
    {
        $rows = DB::table('sales_orders')
            ->selectRaw('DATE(ordered_at) as date, SUM(total_amount) as value')
            ->where('tenant_id', $tenantId)
            ->where('status', 'confirmed')
            ->where('ordered_at', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value])->toArray();
    }

    private function collectInventoryData(int $productId, int $tenantId): array
    {
        $rows = DB::table('stock_movements')
            ->selectRaw('DATE(created_at) as date, SUM(CASE WHEN type = \'in\' THEN quantity ELSE -quantity END) as value')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $rows->map(fn ($r) => ['date' => $r->date, 'value' => (float) $r->value])->toArray();
    }

    // ─── Algorithmes ──────────────────────────────────────────────

    /**
     * Moyenne mobile simple sur N périodes.
     *
     * @param  array<int, array{date: string, value: float}> $data
     * @return array<int, array{date: string, value: float}>
     */
    public function movingAverage(array $data, int $periods = 7): array
    {
        $values = array_column($data, 'value');
        $n      = count($values);

        if ($n === 0) {
            return [];
        }

        $smoothed = [];
        for ($i = 0; $i < $n; $i++) {
            $start         = max(0, $i - $periods + 1);
            $window        = array_slice($values, $start, $i - $start + 1);
            $smoothed[$i]  = array_sum($window) / count($window);
        }

        $lastDate = \Carbon\Carbon::parse($data[$n - 1]['date']);
        $lastAvg  = $smoothed[$n - 1];

        $predictions = [];
        for ($i = 1; $i <= $periods; $i++) {
            $predictions[] = [
                'date'  => $lastDate->copy()->addDays($i)->toDateString(),
                'value' => round($lastAvg, 4),
                'lower' => round($lastAvg * 0.85, 4),
                'upper' => round($lastAvg * 1.15, 4),
            ];
        }

        return $predictions;
    }

    /**
     * Lissage exponentiel de Holt-Winters (triple : niveau + tendance + saisonnalité).
     *
     * @param  array<int, array{date: string, value: float}> $data
     * @return array<int, array{date: string, value: float, lower: float, upper: float}>
     */
    public function exponentialSmoothing(
        array $data,
        float $alpha = 0.3,
        float $beta  = 0.1,
        float $gamma = 0.1
    ): array {
        $values = array_column($data, 'value');
        $n      = count($values);

        if ($n < 2) {
            return [];
        }

        // Initialisation : niveau = première valeur, tendance = différence moyenne
        $level  = $values[0];
        $trend  = ($values[min($n - 1, 1)] - $values[0]);
        $season = array_fill(0, 7, 1.0); // Saisonnalité hebdomadaire (7 jours)

        foreach ($values as $i => $v) {
            $si         = $season[$i % 7];
            $prevLevel  = $level;
            $level      = $alpha * ($v / max($si, 0.001)) + (1 - $alpha) * ($level + $trend);
            $trend      = $beta * ($level - $prevLevel) + (1 - $beta) * $trend;
            $season[$i % 7] = $gamma * ($v / max($level, 0.001)) + (1 - $gamma) * $si;
        }

        $lastDate    = \Carbon\Carbon::parse($data[$n - 1]['date']);
        $predictions = [];
        $stdError    = $this->stdError($values);

        for ($h = 1; $h <= 30; $h++) {
            $forecast      = ($level + $h * $trend) * $season[($n + $h) % 7];
            $halfWidth     = 1.96 * $stdError * sqrt($h);
            $predictions[] = [
                'date'  => $lastDate->copy()->addDays($h)->toDateString(),
                'value' => round(max($forecast, 0), 4),
                'lower' => round(max($forecast - $halfWidth, 0), 4),
                'upper' => round($forecast + $halfWidth, 4),
            ];
        }

        return $predictions;
    }

    /**
     * Régression linéaire (moindres carrés) avec intervalles de confiance à 95 %.
     *
     * @param  array<int, array{date: string, value: float}> $data
     * @return array<int, array{date: string, value: float, lower: float, upper: float}>
     */
    public function linearRegression(array $data, int $horizonDays = 90): array
    {
        $values = array_column($data, 'value');
        $n      = count($values);

        if ($n < 2) {
            return [];
        }

        // Indices temporels x = 0, 1, 2, …, n-1
        $x     = range(0, $n - 1);
        $xMean = array_sum($x) / $n;
        $yMean = array_sum($values) / $n;

        $ssXX = $ssXY = 0.0;
        foreach ($x as $i) {
            $ssXX += ($i - $xMean) ** 2;
            $ssXY += ($i - $xMean) * ($values[$i] - $yMean);
        }

        $slope     = $ssXX > 0 ? $ssXY / $ssXX : 0.0;
        $intercept = $yMean - $slope * $xMean;

        // Erreur standard des résidus
        $ssRes = 0.0;
        foreach ($x as $i) {
            $ssRes += ($values[$i] - ($intercept + $slope * $i)) ** 2;
        }
        $stdErr  = $n > 2 ? sqrt($ssRes / ($n - 2)) : 0.0;
        $seSlope = $ssXX > 0 ? $stdErr / sqrt($ssXX) : 0.0;

        $lastDate    = \Carbon\Carbon::parse($data[$n - 1]['date']);
        $predictions = [];

        for ($h = 1; $h <= $horizonDays; $h++) {
            $xNew      = $n - 1 + $h;
            $forecast  = $intercept + $slope * $xNew;
            // IC 95 % : ±1.96 * se_prévision
            $seForecast = $stdErr * sqrt(1 + 1 / $n + ($xNew - $xMean) ** 2 / $ssXX);
            $halfWidth  = 1.96 * $seForecast;

            $predictions[] = [
                'date'  => $lastDate->copy()->addDays($h)->toDateString(),
                'value' => round(max($forecast, 0), 4),
                'lower' => round(max($forecast - $halfWidth, 0), 4),
                'upper' => round($forecast + $halfWidth, 4),
            ];
        }

        return $predictions;
    }

    /**
     * Décomposition saisonnière (tendance + saisonnalité + résidu).
     *
     * @param  array<int, array{date: string, value: float}> $data
     * @return array{trend: float[], seasonal: float[], residual: float[]}
     */
    public function decompose(array $data): array
    {
        $values = array_column($data, 'value');
        $n      = count($values);
        $period = 7; // hebdomadaire

        if ($n < $period * 2) {
            return ['trend' => $values, 'seasonal' => array_fill(0, $n, 1.0), 'residual' => array_fill(0, $n, 0.0)];
        }

        // Tendance : moyenne mobile centrée sur `period`
        $trend = [];
        for ($i = 0; $i < $n; $i++) {
            $start     = max(0, $i - (int) floor($period / 2));
            $end       = min($n - 1, $i + (int) floor($period / 2));
            $window    = array_slice($values, $start, $end - $start + 1);
            $trend[$i] = array_sum($window) / count($window);
        }

        // Saisonnalité : moyenne par position dans la période
        $seasonal = array_fill(0, $n, 1.0);
        for ($p = 0; $p < $period; $p++) {
            $indices = [];
            for ($i = $p; $i < $n; $i += $period) {
                if ($trend[$i] > 0) {
                    $indices[] = $values[$i] / $trend[$i];
                }
            }
            $avg = count($indices) > 0 ? array_sum($indices) / count($indices) : 1.0;
            for ($i = $p; $i < $n; $i += $period) {
                $seasonal[$i] = $avg;
            }
        }

        // Résidu
        $residual = [];
        for ($i = 0; $i < $n; $i++) {
            $residual[$i] = $seasonal[$i] > 0 ? $values[$i] / $seasonal[$i] - $trend[$i] : 0.0;
        }

        return compact('trend', 'seasonal', 'residual');
    }

    // ─── Prévision IA (Claude) ────────────────────────────────────

    /**
     * Prévision narrative par Claude avec explication des facteurs clés.
     * Utilise le prompt caching pour réduire les coûts.
     * Fallback gracieux vers linearRegression() si l'API est indisponible.
     *
     * @param  array<int, array{date: string, value: float}> $historicalData
     * @return array{predictions: array, narrative: string, key_factors: string[], risks: string[], confidence: float, recommended_actions: array}
     */
    public function aiforecast(
        array  $historicalData,
        string $module,
        string $context  = '',
        string $locale   = 'fr'
    ): array {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey) || count($historicalData) < 5) {
            return $this->aiFallback($historicalData, $module);
        }

        // Résumé anonymisé de la série (max 90 derniers points)
        $summary = array_slice($historicalData, -90);
        $values  = array_column($summary, 'value');

        $systemPrompt = <<<SYSTEM
Tu es un expert en prévision économique pour les marchés africains et asiatiques.
Tu analyses des séries temporelles d'entreprises (demande, trésorerie, RH, production, revenus, stocks).
Tu fournis des prévisions chiffrées sur 30, 60 et 90 jours avec des explications contextuelles.
Tes réponses sont en {$locale}. Sois précis, pratique et adapté aux PME africaines (XOF/XAF, OHADA).
SYSTEM;

        $userMessage = <<<USER
Module : {$module}
Contexte : {$context}
Données historiques (anonymisées, {$locale}) :
Dernières valeurs : {$this->formatSeries($summary)}
Statistiques : min={$this->min($values)}, max={$this->max($values)}, moyenne={$this->mean($values)}, tendance={$this->trendLabel($values)}

Génère une prévision structurée au format JSON :
{
  "predictions": [{"date":"YYYY-MM-DD","value":0,"lower":0,"upper":0}],  // 30 et 90 jours
  "narrative": "...",
  "key_factors": ["..."],
  "risks": ["..."],
  "confidence": 0.0,
  "recommended_actions": [{"label":"...","action":"...","module":"..."}]
}
USER;

        try {
            $cacheKey = 'ai_forecast_' . md5($module . $context . $locale . json_encode(array_slice($values, -10)));

            $response = Cache::remember($cacheKey, 300, function () use ($apiKey, $systemPrompt, $userMessage) {
                return Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                    'model'      => 'claude-sonnet-4-6',
                    'max_tokens' => 1200,
                    'system'     => [
                        [
                            'type'          => 'text',
                            'text'          => $systemPrompt,
                            'cache_control' => ['type' => 'ephemeral'],
                        ],
                    ],
                    'messages'   => [
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ])->json();
            });

            $text   = $response['content'][0]['text'] ?? '';
            $parsed = $this->parseJsonFromText($text);

            if ($parsed && isset($parsed['predictions'])) {
                return array_merge([
                    'narrative'           => '',
                    'key_factors'         => [],
                    'risks'               => [],
                    'confidence'          => 0.75,
                    'recommended_actions' => [],
                ], $parsed);
            }
        } catch (\Throwable $e) {
            Log::warning('ForecastingEngineService: AI forecast failed, using fallback.', [
                'module' => $module,
                'error'  => $e->getMessage(),
            ]);
        }

        return $this->aiFallback($historicalData, $module);
    }

    private function aiFallback(array $historicalData, string $module): array
    {
        $predictions = $this->linearRegression($historicalData, 90);
        $values      = array_column($historicalData, 'value');
        $trend       = $this->trendLabel($values);

        return [
            'predictions'         => $predictions,
            'narrative'           => "Prévision basée sur la régression linéaire ({$module}). Tendance : {$trend}.",
            'key_factors'         => ['Régression linéaire (mode hors-ligne)'],
            'risks'               => ['Données insuffisantes pour une analyse IA approfondie'],
            'confidence'          => 0.60,
            'recommended_actions' => [],
            'enabled'             => false,
        ];
    }

    // ─── Entraînement & Prédiction ────────────────────────────────

    /**
     * Entraîne (ou réentraîne) un modèle et génère les prédictions.
     */
    public function train(ForecastModel $model): ForecastModel
    {
        $data = $this->collectHistoricalData(
            $model->module,
            $model->entity_type ?? 'global',
            (int) $model->entity_id,
            $model->tenant_id
        );

        if (empty($data)) {
            Log::info("ForecastingEngine: aucune donnée pour le modèle #{$model->id}");
            return $model;
        }

        // Division entraînement / validation (80 % / 20 %)
        $cutoff   = (int) floor(count($data) * 0.8);
        $trainSet = array_slice($data, 0, $cutoff);
        $testSet  = array_slice($data, $cutoff);

        // Générer des prédictions sur le jeu de test pour calculer le MAPE
        $testPredictions = $this->runAlgorithm($model, $trainSet, count($testSet));
        $mape            = $this->calculateMape($testSet, $testPredictions);
        $confidence      = max(0.0, min(1.0, 1 - $mape / 100));

        $model->update([
            'confidence_level' => $confidence,
            'last_trained_at'  => now(),
        ]);

        // Générer les prédictions réelles sur l'horizon complet
        $predictions = $this->runAlgorithm($model, $data, $model->horizon_days);
        $this->storePredictions($model, $predictions);

        $model->scheduleNextRetrain();

        return $model->fresh();
    }

    /**
     * Génère des prédictions fraîches pour un modèle existant.
     *
     * @return array<int, array{date: string, value: float, lower: float, upper: float}>
     */
    public function predict(ForecastModel $model, int $horizonDays = null): array
    {
        $horizon = $horizonDays ?? $model->horizon_days;
        $data    = $this->collectHistoricalData(
            $model->module,
            $model->entity_type ?? 'global',
            (int) $model->entity_id,
            $model->tenant_id
        );

        return $this->runAlgorithm($model, $data, $horizon);
    }

    /**
     * Backfill nocturne : compare les prédictions passées aux valeurs réelles.
     */
    public function backfill(int $tenantId): void
    {
        ForecastPrediction::where('tenant_id', $tenantId)
            ->unfilled()
            ->with('model')
            ->chunk(200, function ($predictions) {
                foreach ($predictions as $prediction) {
                    try {
                        $actual = $this->fetchActualValue($prediction);
                        if ($actual !== null) {
                            $errorPct = abs($prediction->predicted_value - $actual)
                                       / max($actual, 0.001) * 100;

                            $prediction->update([
                                'actual_value' => $actual,
                                'error_pct'    => $errorPct,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Backfill échoué pour la prédiction #{$prediction->id}: {$e->getMessage()}");
                    }
                }
            });
    }

    private function fetchActualValue(ForecastPrediction $prediction): ?float
    {
        $model = $prediction->model;
        if (! $model) {
            return null;
        }

        $date = $prediction->forecast_date->toDateString();

        return match ($model->module) {
            'revenue' => DB::table('sales_orders')
                ->where('tenant_id', $prediction->tenant_id)
                ->whereDate('ordered_at', $date)
                ->where('status', 'confirmed')
                ->sum('total_amount'),

            'demand' => DB::table('sales_order_lines as sol')
                ->join('sales_orders as so', 'so.id', '=', 'sol.order_id')
                ->where('so.tenant_id', $prediction->tenant_id)
                ->whereDate('so.ordered_at', $date)
                ->when($model->entity_id, fn ($q) => $q->where('sol.product_id', $model->entity_id))
                ->sum('sol.quantity'),

            default => null,
        };
    }

    // ─── Moteur d'alertes ─────────────────────────────────────────

    /**
     * Analyse toutes les prévisions actives et crée les alertes nécessaires.
     *
     * @return int Nombre d'alertes créées
     */
    public function checkAlerts(int $tenantId): int
    {
        $count  = 0;
        $models = ForecastModel::forTenant($tenantId)->active()->get();

        foreach ($models as $model) {
            $count += $this->checkModelAlerts($model);
        }

        return $count;
    }

    private function checkModelAlerts(ForecastModel $model): int
    {
        $count       = 0;
        $predictions = $model->predictions()
            ->where('forecast_date', '>=', now()->toDateString())
            ->orderBy('forecast_date')
            ->get();

        if ($predictions->isEmpty()) {
            return 0;
        }

        $values = $predictions->pluck('predicted_value')->map(fn ($v) => (float) $v)->toArray();
        $avg    = array_sum($values) / count($values);

        // Alerte : risque de rupture de stock
        if ($model->module === 'inventory') {
            $currentStock = $this->getCurrentStock((int) $model->entity_id, $model->tenant_id);
            $totalDemand  = array_sum(array_slice($values, 0, 30));
            if ($totalDemand > $currentStock * 0.9) {
                $this->createAlert($model, [
                    'alert_type'      => 'stockout_risk',
                    'severity'        => $totalDemand > $currentStock ? 'critical' : 'warning',
                    'title'           => 'Risque de rupture de stock',
                    'message'         => "La demande prévue ({$totalDemand}) dépasse le stock disponible ({$currentStock}) dans les 30 prochains jours.",
                    'predicted_value' => $totalDemand,
                    'threshold_value' => $currentStock,
                ]);
                $count++;
            }
        }

        // Alerte : déficit de trésorerie
        if ($model->module === 'cashflow') {
            $minBalance = min($values);
            if ($minBalance < 0) {
                $deficitDate = $predictions[$predictions->search(fn ($p) => (float) $p->predicted_value === $minBalance)];
                $this->createAlert($model, [
                    'alert_type'      => 'cashflow_deficit',
                    'severity'        => $minBalance < -100000 ? 'critical' : 'warning',
                    'title'           => 'Déficit de trésorerie prévu',
                    'message'         => "La trésorerie devrait être négative ({$minBalance} XOF) à partir du {$deficitDate?->forecast_date}.",
                    'predicted_date'  => $deficitDate?->forecast_date,
                    'predicted_value' => $minBalance,
                    'threshold_value' => 0,
                ]);
                $count++;
            }
        }

        // Alerte : pic de demande (> 1,5× la moyenne)
        if ($model->module === 'demand' || $model->module === 'revenue') {
            $peak = max($values);
            if ($peak > $avg * 1.5 && $avg > 0) {
                $this->createAlert($model, [
                    'alert_type'      => 'above_threshold',
                    'severity'        => 'info',
                    'title'           => 'Pic de demande prévu',
                    'message'         => "Un pic de demande ({$peak}) est prévu, soit 50 % au-dessus de la moyenne ({$avg}).",
                    'predicted_value' => $peak,
                    'threshold_value' => $avg * 1.5,
                ]);
                $count++;
            }
        }

        return $count;
    }

    private function createAlert(ForecastModel $model, array $data): void
    {
        // Éviter les doublons : une alerte par type et par modèle dans les 24 h
        $exists = ForecastAlert::where('model_id', $model->id)
            ->where('alert_type', $data['alert_type'])
            ->where('is_acknowledged', false)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if (! $exists) {
            ForecastAlert::create(array_merge($data, [
                'tenant_id' => $model->tenant_id,
                'model_id'  => $model->id,
            ]));
        }
    }

    // ─── Scénarios ────────────────────────────────────────────────

    /**
     * Crée un scénario "et si…" avec des hypothèses modifiées.
     */
    public function createScenario(int $modelId, string $name, array $assumptions): ForecastScenario
    {
        $model = ForecastModel::findOrFail($modelId);
        $data  = $this->collectHistoricalData(
            $model->module,
            $model->entity_type ?? 'global',
            (int) $model->entity_id,
            $model->tenant_id
        );

        // Appliquer les hypothèses à la série historique
        $modifiedData = $this->applyAssumptions($data, $assumptions);
        $predictions  = $this->runAlgorithm($model, $modifiedData, $model->horizon_days);

        return ForecastScenario::create([
            'tenant_id'     => $model->tenant_id,
            'name'          => $name,
            'base_model_id' => $modelId,
            'assumptions'   => $assumptions,
            'results'       => ['predictions' => $predictions],
            'created_by'    => auth()->id(),
        ]);
    }

    /**
     * Comparaison côte à côte de plusieurs scénarios.
     */
    public function compareScenarios(array $scenarioIds): array
    {
        $scenarios = ForecastScenario::whereIn('id', $scenarioIds)->get();

        return $scenarios->map(function ($scenario) {
            $predictions = $scenario->results['predictions'] ?? [];
            $values      = array_column($predictions, 'value');

            return [
                'id'          => $scenario->id,
                'name'        => $scenario->name,
                'assumptions' => $scenario->assumptions,
                'total'       => array_sum($values),
                'avg'         => count($values) > 0 ? array_sum($values) / count($values) : 0,
                'min'         => count($values) > 0 ? min($values) : 0,
                'max'         => count($values) > 0 ? max($values) : 0,
                'predictions' => $predictions,
                'created_at'  => $scenario->created_at,
            ];
        })->toArray();
    }

    // ─── Méthodes privées utilitaires ─────────────────────────────

    private function runAlgorithm(ForecastModel $model, array $data, int $horizon): array
    {
        if (empty($data)) {
            return [];
        }

        $config = $model->config ?? [];

        return match ($model->algorithm) {
            'moving_average'        => $this->movingAverage($data, $config['periods'] ?? 7),
            'exponential_smoothing' => $this->exponentialSmoothing(
                $data,
                $config['alpha'] ?? 0.3,
                $config['beta']  ?? 0.1,
                $config['gamma'] ?? 0.1
            ),
            'ai_claude'             => $this->aiforecast($data, $model->module)['predictions'] ?? [],
            default                 => $this->linearRegression($data, $horizon), // linear_regression
        };
    }

    private function storePredictions(ForecastModel $model, array $predictions): void
    {
        // Supprimer les prévisions futures existantes
        ForecastPrediction::where('model_id', $model->id)
            ->where('forecast_date', '>', now()->toDateString())
            ->delete();

        $rows = array_map(fn ($p) => [
            'model_id'              => $model->id,
            'tenant_id'             => $model->tenant_id,
            'forecast_date'         => $p['date'],
            'predicted_value'       => $p['value'],
            'lower_bound'           => $p['lower'] ?? null,
            'predicted_upper_bound' => $p['upper'] ?? null,
            'confidence'            => $model->confidence_level ?? 0.8,
            'created_at'            => now(),
        ], $predictions);

        foreach (array_chunk($rows, 100) as $chunk) {
            ForecastPrediction::insert($chunk);
        }
    }

    private function calculateMape(array $actual, array $predicted): float
    {
        $actualValues    = array_column($actual, 'value');
        $predictedValues = array_column($predicted, 'value');
        $n               = min(count($actualValues), count($predictedValues));

        if ($n === 0) {
            return 100.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $a    = $actualValues[$i];
            $sum += $a > 0 ? abs($a - $predictedValues[$i]) / $a : 0;
        }

        return ($sum / $n) * 100;
    }

    private function applyAssumptions(array $data, array $assumptions): array
    {
        $growthRate    = $assumptions['growth_rate']    ?? 0.0;
        $priceIncrease = $assumptions['price_increase'] ?? 0.0;

        return array_map(function ($point) use ($growthRate, $priceIncrease) {
            return [
                'date'  => $point['date'],
                'value' => $point['value'] * (1 + $growthRate) * (1 + $priceIncrease),
            ];
        }, $data);
    }

    private function getCurrentStock(int $productId, int $tenantId): float
    {
        return (float) DB::table('stock_levels')
            ->where('product_id', $productId)
            ->where('tenant_id', $tenantId)
            ->value('quantity') ?? 0.0;
    }

    private function stdError(array $values): float
    {
        $n    = count($values);
        $mean = array_sum($values) / max($n, 1);
        $var  = 0.0;
        foreach ($values as $v) {
            $var += ($v - $mean) ** 2;
        }

        return $n > 1 ? sqrt($var / ($n - 1)) : 0.0;
    }

    private function mean(array $values): float
    {
        return count($values) > 0 ? round(array_sum($values) / count($values), 2) : 0.0;
    }

    private function min(array $values): float
    {
        return count($values) > 0 ? min($values) : 0.0;
    }

    private function max(array $values): float
    {
        return count($values) > 0 ? max($values) : 0.0;
    }

    private function trendLabel(array $values): string
    {
        $n = count($values);
        if ($n < 2) {
            return 'stable';
        }
        $first = array_sum(array_slice($values, 0, (int) ($n / 4))) / max((int) ($n / 4), 1);
        $last  = array_sum(array_slice($values, -(int) ($n / 4))) / max((int) ($n / 4), 1);
        if ($last > $first * 1.05) {
            return 'hausse';
        }
        if ($last < $first * 0.95) {
            return 'baisse';
        }

        return 'stable';
    }

    private function formatSeries(array $data): string
    {
        return implode(', ', array_map(
            fn ($p) => "{$p['date']}:{$p['value']}",
            array_slice($data, -15)
        ));
    }

    private function parseJsonFromText(string $text): ?array
    {
        // Extraire le premier bloc JSON valide du texte
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }
}
