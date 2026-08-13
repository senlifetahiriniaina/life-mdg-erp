<?php

namespace Modules\Analytics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Services\ForecastingEngineService;

/**
 * Job noctune de prévision.
 *
 * Exécuté chaque nuit via le scheduler Laravel :
 *   1. Réentraîne les modèles dont la date next_retrain_at est dépassée
 *   2. Backfille les valeurs réelles pour les prévisions passées
 *   3. Vérifie et crée les alertes de dépassement de seuil
 *
 * Configuration :
 *   tries   = 3
 *   timeout = 300 secondes
 */
class RunForecastingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(
        private readonly int    $tenantId,
        private readonly string $module      = '',
        private readonly bool   $forceRetrain = false
    ) {}

    public function handle(ForecastingEngineService $engine): void
    {
        Log::info("RunForecastingJob: démarrage pour tenant #{$this->tenantId}", [
            'module'       => $this->module ?: 'tous',
            'forceRetrain' => $this->forceRetrain,
        ]);

        $startTime = microtime(true);

        // ── Étape 1 : Réentraînement des modèles dus ──────────────
        $this->retrainModels($engine);

        // ── Étape 2 : Backfill des valeurs réelles ────────────────
        $engine->backfill($this->tenantId);
        Log::info("RunForecastingJob: backfill terminé pour tenant #{$this->tenantId}");

        // ── Étape 3 : Vérification des alertes ────────────────────
        $alertCount = $engine->checkAlerts($this->tenantId);
        Log::info("RunForecastingJob: {$alertCount} alertes créées/vérifiées pour tenant #{$this->tenantId}");

        $elapsed = round(microtime(true) - $startTime, 2);
        Log::info("RunForecastingJob: terminé en {$elapsed}s pour tenant #{$this->tenantId}");
    }

    private function retrainModels(ForecastingEngineService $engine): void
    {
        $query = ForecastModel::forTenant($this->tenantId);

        if ($this->module) {
            $query->where('module', $this->module);
        }

        if ($this->forceRetrain) {
            $query->where('is_active', true);
        } else {
            $query->dueForRetraining();
        }

        $models = $query->get();
        Log::info("RunForecastingJob: {$models->count()} modèle(s) à entraîner");

        foreach ($models as $model) {
            try {
                $engine->train($model);
                Log::info("RunForecastingJob: modèle #{$model->id} '{$model->name}' entraîné avec succès");
            } catch (\Throwable $e) {
                Log::error("RunForecastingJob: erreur entraînement modèle #{$model->id}: {$e->getMessage()}");
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RunForecastingJob: échec définitif pour tenant #{$this->tenantId}", [
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Dispatche le job pour tous les tenants actifs.
     */
    public static function dispatchForAllTenants(): void
    {
        $tenantIds = DB::table('companies')
            ->where('is_active', true)
            ->pluck('id');

        foreach ($tenantIds as $tenantId) {
            self::dispatch($tenantId);
        }
    }
}
