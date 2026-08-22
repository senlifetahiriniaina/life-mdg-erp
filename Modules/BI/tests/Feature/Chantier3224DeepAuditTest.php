<?php

declare(strict_types=1);

/**
 * Chantier 32.24 — BI 14-layer deep audit (méthodologie décrite dans CLAUDE.md
 * § "Méthodologie d'audit approfondi (14 couches, à partir du Chantier 32)").
 *
 * Locks in:
 *  - layer 9 (fake/dead): the 14 theatrical `extends BaseAsyncJob` jobs (rand()-
 *    based fake data, hardcoded strings, zero real callers anywhere in the app)
 *    plus 6 orphaned duplicate services are confirmed gone — a negative
 *    class_exists() assertion per class, not merely "the file was deleted".
 *  - layer 10 (relational): `BiAlert::biQuery()` had an implicit FK
 *    (`bi_query_id`) that never matched the real column (`query_id`) — fixed
 *    to an explicit FK.
 *  - layer 8 (validation métier) + layer 4 (real execution): `AlertService`'s
 *    `checkAlert()`/new `refreshValue()` actually resolve a real current value
 *    from the alert's linked `BiQuery` via the already-live `QueryRunnerService`
 *    before evaluating — before this chantier, `bi_alerts.last_value` was never
 *    written by any reachable code path, so `AlertController::test()` always
 *    returned `triggered: false` regardless of the real underlying data.
 *  - layer 1 (route)/scheduling: `CheckBiAlertsJob` (the module's one real,
 *    correctly-written periodic sweep) is now actually scheduled.
 */

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Modules\BI\Jobs\CheckBiAlertsJob;
use Modules\BI\Models\BiAlert;
use Modules\BI\Models\BiQuery;
use Modules\BI\Services\AlertService;

// ─── Layer 9: confirmed-dead jobs/services are actually gone ─────────────────

describe('Chantier 32.24 — dead job/service cleanup', function () {
    it('the 14 confirmed-theatrical BaseAsyncJob subclasses no longer exist', function () {
        $deletedJobs = [
            'Modules\BI\Jobs\AnalyzeStoryEngagementJob',
            'Modules\BI\Jobs\CreateDataStoryJob',
            'Modules\BI\Jobs\EscalateUnacknowledgedAlertsJob',
            'Modules\BI\Jobs\EvaluateAlertRulesJob',
            'Modules\BI\Jobs\EvaluateForecastAccuracyJob',
            'Modules\BI\Jobs\ExportVisualizationJob',
            'Modules\BI\Jobs\GenerateAlertDigestJob',
            'Modules\BI\Jobs\GenerateForecastJob',
            'Modules\BI\Jobs\GenerateVisualizationDataJob',
            'Modules\BI\Jobs\PublishDataStoryJob',
            'Modules\BI\Jobs\RenderCustomChartJob',
            'Modules\BI\Jobs\SendAlertNotificationJob',
            'Modules\BI\Jobs\SyncExternalDataSourceJob',
            'Modules\BI\Jobs\TrainForecastModelJob',
            'Modules\BI\Jobs\TransformAndValidateDataJob',
        ];

        foreach ($deletedJobs as $class) {
            expect(class_exists($class))->toBeFalse("{$class} should have been deleted (confirmed dead/fake).");
        }
    });

    it('the real CheckBiAlertsJob still exists (the one real periodic job, not deleted)', function () {
        expect(class_exists(CheckBiAlertsJob::class))->toBeTrue();
    });

    it('the 6 confirmed-orphaned duplicate services no longer exist', function () {
        $deletedServices = [
            'Modules\BI\Services\RealTimeAlertService',
            'Modules\BI\Services\ExternalDataIntegrationService',
            'Modules\BI\Services\DataConnectorService',
            'Modules\BI\Services\EnhancedPredictiveAnalyticsService',
            'Modules\BI\Services\DataStorytellingService',
            'Modules\BI\Services\AdvancedVisualizationService',
        ];

        foreach ($deletedServices as $class) {
            expect(class_exists($class))->toBeFalse("{$class} should have been deleted (confirmed dead duplicate, zero real callers).");
        }
    });

    it('the real, still-live services survive the cleanup', function () {
        expect(class_exists(\Modules\BI\Services\AlertService::class))->toBeTrue();
        expect(class_exists(\Modules\BI\Services\DataSourceService::class))->toBeTrue();
        expect(class_exists(\Modules\BI\Services\PredictiveAnalyticsService::class))->toBeTrue();
        expect(class_exists(\Modules\BI\Services\QueryRunnerService::class))->toBeTrue();
        expect(class_exists(\Modules\BI\Services\ExportService::class))->toBeTrue();
        expect(class_exists(\Modules\BI\Services\DrillDownService::class))->toBeTrue();
        expect(class_exists(\Modules\BI\Services\EmbedTokenService::class))->toBeTrue();
    });
});

// ─── Layer 10 + layer 8/4: AlertService real-value resolution ────────────────

describe('Chantier 32.24 — AlertService real current-value resolution', function () {
    it('BiAlert::biQuery() resolves via the real query_id column, not the implicit bi_query_id default', function () {
        $query = BiQuery::factory()->create();
        $alert = BiAlert::factory()->create(['query_id' => $query->id]);

        expect($alert->biQuery)->not->toBeNull();
        expect($alert->biQuery->id)->toBe($query->id);
    });

    it('AlertService::refreshValue resolves a real value from the linked query via QueryRunnerService', function () {
        $query = BiQuery::create([
            'name' => 'metric probe',
            'sql_query' => 'SELECT 77 as metric_value',
            'result_cache_ttl' => 0,
        ]);
        $alert = BiAlert::create([
            'name' => 'probe alert',
            'query_id' => $query->id,
            'condition_type' => 'above',
            'threshold' => 10,
            'metric_name' => 'metric_value',
            'check_interval_minutes' => 15,
            'channels' => ['email'],
            'recipients' => [],
            'status' => 'active',
        ]);

        expect($alert->last_value)->toBeNull();

        $value = app(AlertService::class)->refreshValue($alert);

        expect($value)->toBe(77.0);
    });

    it('checkAlert persists the refreshed real value and evaluates against it, not a stale/null value', function () {
        $query = BiQuery::create([
            'name' => 'threshold probe',
            'sql_query' => 'SELECT 5 as metric_value',
            'result_cache_ttl' => 0,
        ]);
        $alert = BiAlert::create([
            'name' => 'below-threshold probe',
            'query_id' => $query->id,
            'condition_type' => 'above',
            'threshold' => 100,
            'metric_name' => 'metric_value',
            'check_interval_minutes' => 15,
            'channels' => ['email'],
            'recipients' => [],
            'status' => 'active',
        ]);

        $triggered = app(AlertService::class)->checkAlert($alert);

        expect($triggered)->toBeFalse();
        expect($alert->fresh()->last_value)->toBe(5.0);
        expect($alert->fresh()->last_checked_at)->not->toBeNull();
    });

    it('a widget-only alert (no query_id) degrades gracefully — refreshValue returns null, no crash', function () {
        $alert = BiAlert::factory()->create(['query_id' => null, 'widget_id' => null, 'last_value' => null]);

        $value = app(AlertService::class)->refreshValue($alert);

        expect($value)->toBeNull();
        expect(fn () => app(AlertService::class)->checkAlert($alert))->not->toThrow(\Throwable::class);
    });

    it('POST /api/v1/bi/alerts/{alert}/test now returns a real triggered state, confirmed end-to-end over HTTP', function () {
        actingAsUser('manager');

        $query = BiQuery::create([
            'name' => 'http probe',
            'sql_query' => 'SELECT 999 as metric_value',
            'result_cache_ttl' => 0,
        ]);
        $alert = BiAlert::create([
            'name' => 'http alert',
            'query_id' => $query->id,
            'condition_type' => 'above',
            'threshold' => 500,
            'metric_name' => 'metric_value',
            'check_interval_minutes' => 15,
            'channels' => ['email'],
            'recipients' => [],
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/bi/alerts/{$alert->id}/test")
            ->assertOk()
            ->assertJsonPath('triggered', true);

        expect((float) $response->json('last_value'))->toBe(999.0);
    });
});

// ─── Layer 1/scheduling: CheckBiAlertsJob is now actually scheduled ──────────

describe('Chantier 32.24 — CheckBiAlertsJob scheduling', function () {
    it('is registered on the application schedule under a real, named entry', function () {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $names = collect($events)->map(fn ($e) => $e->description ?? null)->filter()->values();

        expect($names->contains('bi:check-alerts'))->toBeTrue();
    });
});
