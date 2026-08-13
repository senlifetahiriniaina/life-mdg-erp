<?php

namespace Modules\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\Jobs\RunForecastingJob;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Services\ForecastingEngineService;

/**
 * Commande Artisan de prévision.
 *
 * Utilisation :
 *   php artisan forecast:run                          # tous les tenants, tous les modules
 *   php artisan forecast:run --tenant=1               # tenant spécifique
 *   php artisan forecast:run --tenant=1 --module=demand
 *   php artisan forecast:run --tenant=1 --retrain     # forcer le réentraînement
 */
class ForecastCommand extends Command
{
    protected $signature = 'forecast:run
        {--tenant= : ID du tenant (tous si absent)}
        {--module= : Module spécifique (demand|cashflow|hr|production|revenue|inventory)}
        {--retrain : Forcer le réentraînement de tous les modèles actifs}
        {--sync    : Exécuter de manière synchrone (sans queue)}';

    protected $description = 'Lance le moteur de prévision IA — réentraînement, backfill et alertes';

    public function handle(ForecastingEngineService $engine): int
    {
        $tenantOption = $this->option('tenant');
        $module       = $this->option('module') ?? '';
        $forceRetrain = (bool) $this->option('retrain');
        $sync         = (bool) $this->option('sync');

        $tenantIds = $tenantOption
            ? [(int) $tenantOption]
            : DB::table('companies')->where('is_active', true)->pluck('id')->toArray();

        if (empty($tenantIds)) {
            $this->warn('Aucun tenant actif trouvé.');
            return self::SUCCESS;
        }

        $this->info("Moteur de prévision IA — Phase 41");
        $this->info("Tenants : " . implode(', ', $tenantIds));
        $this->info("Module  : " . ($module ?: 'tous'));
        $this->info("Réentraînement forcé : " . ($forceRetrain ? 'oui' : 'non'));
        $this->newLine();

        foreach ($tenantIds as $tenantId) {
            if ($sync) {
                $this->runSync($engine, $tenantId, $module, $forceRetrain);
            } else {
                RunForecastingJob::dispatch($tenantId, $module, $forceRetrain);
                $this->line("  ✓ Job dispatché pour tenant #{$tenantId}");
            }
        }

        $this->newLine();
        $this->info($sync ? 'Prévisions terminées.' : 'Jobs mis en file d\'attente.');

        return self::SUCCESS;
    }

    private function runSync(
        ForecastingEngineService $engine,
        int    $tenantId,
        string $module,
        bool   $forceRetrain
    ): void {
        $this->line("  Tenant #{$tenantId} …");

        // Sélection des modèles
        $query = ForecastModel::forTenant($tenantId)->where('is_active', true);
        if ($module) {
            $query->where('module', $module);
        }
        if (! $forceRetrain) {
            $query->dueForRetraining();
        }

        $models = $query->get();
        $bar    = $this->output->createProgressBar($models->count());
        $bar->start();

        foreach ($models as $model) {
            try {
                $engine->train($model);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("  Erreur modèle #{$model->id}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Backfill
        $this->line("  Backfill des valeurs réelles…");
        $engine->backfill($tenantId);

        // Alertes
        $alertCount = $engine->checkAlerts($tenantId);
        $this->line("  {$alertCount} alerte(s) vérifiée(s).");
    }
}
