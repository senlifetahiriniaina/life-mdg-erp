<?php

declare(strict_types=1);

namespace Modules\Strategy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Strategy\Services\StrategyRatioService;

/**
 * Chantier 32.27 (audit 14 couches — layer 9, fake/dead → « à activer »):
 * StrategyRatioService::storeSnapshot()/getHistory() and the `RatioSnapshot`
 * model/table were a fully real, tested mechanism with zero real producer
 * anywhere in the app — confirmed via exhaustive grep, only referenced by
 * their own class and StrategyRatioServiceTest. As a direct consequence,
 * Ratios/Index.vue's per-ratio sparkline never showed real historical data
 * despite this module's own earlier changelog entry describing it as
 * "real trend data" — StrategyRatioService::enrichRatio() actually called
 * generateMockTrend(), which re-randomizes 6 fake points around the
 * current value on every single page load (mt_rand-based noise, not even
 * deterministic). This command is the missing real producer: for every
 * active company, snapshot the current value of every canonical ratio
 * definition once — the same pattern already established by
 * Modules\Analytics\Console\Commands\ForecastCommand for "iterate every
 * active tenant". Scheduled daily via
 * StrategyServiceProvider::registerCommandSchedules() (previously an empty
 * stub, the one real gap this closes at the provider level too).
 *
 *   php artisan strategy:snapshot-ratios              # all active companies
 *   php artisan strategy:snapshot-ratios --tenant=1   # one company
 */
class SnapshotRatiosCommand extends Command
{
    protected $signature = 'strategy:snapshot-ratios {--tenant= : Company id (all active companies if omitted)}';

    protected $description = 'Snapshot the current value of every Strategy First ratio, per company, for real historical trends';

    public function handle(StrategyRatioService $ratioService): int
    {
        $tenantOption = $this->option('tenant');

        $tenantIds = $tenantOption
            ? [(int) $tenantOption]
            : DB::table('companies')->where('is_active', true)->pluck('id')->all();

        if (empty($tenantIds)) {
            $this->info('No active company found — nothing to snapshot.');
            return self::SUCCESS;
        }

        $definitions = $ratioService->ratioDefinitions();
        $count       = 0;

        foreach ($tenantIds as $tenantId) {
            $tenantId = (string) $tenantId;

            foreach ($definitions as $module => $ratios) {
                foreach ($ratios as $ratio) {
                    [$defModule, $key] = explode(':', $ratio['kpi_key'] . ':');
                    $value  = $ratioService->calculate($defModule, $key, 1.0, $tenantId);
                    $status = 'amber';
                    if (isset($ratio['target_min'])) {
                        $status = $value >= $ratio['target_min'] ? 'green' : 'red';
                    } elseif (isset($ratio['target_max'])) {
                        $status = $value <= $ratio['target_max'] ? 'green' : 'red';
                    }

                    $ratioService->storeSnapshot(
                        tenantId: $tenantId,
                        module: $module,
                        ratioKey: $ratio['key'],
                        value: $value,
                        benchmark: $ratio['target_min'] ?? $ratio['target_max'] ?? null,
                        status: $status,
                    );
                    $count++;
                }
            }
        }

        $this->info("Snapshotted {$count} ratio values across ".count($tenantIds).' company(ies).');

        return self::SUCCESS;
    }
}
