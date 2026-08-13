<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Pipeline;
use Modules\CRM\Models\PipelineSnapshot;
use Modules\CRM\Models\WinLossRecord;
use Modules\CRM\Services\PipelineAnalyticsService;


// ─── WinLossRecord Model ──────────────────────────────────────────────────────

describe('WinLossRecord Model', function () {
    test('has correct table name', function () {
        $pipeline = Pipeline::factory()->create();
        $opp = Opportunity::factory()->create(['pipeline_id' => $pipeline->id]);
        $record = WinLossRecord::create([
            'opportunity_id' => $opp->id,
            'outcome' => 'won',
            'deal_value' => 1000,
            'recorded_at' => now(),
        ]);

        expect($record->getTable())->toBe('crm_win_loss_records');
    });

    test('isWon returns true for won outcome', function () {
        $record = new WinLossRecord(['outcome' => 'won']);
        expect($record->isWon())->toBeTrue();
        expect($record->isLost())->toBeFalse();
    });

    test('isLost returns true for lost outcome', function () {
        $record = new WinLossRecord(['outcome' => 'lost']);
        expect($record->isLost())->toBeTrue();
        expect($record->isWon())->toBeFalse();
    });

    test('deal_value is cast to decimal', function () {
        $pipeline = Pipeline::factory()->create();
        $opp = Opportunity::factory()->create(['pipeline_id' => $pipeline->id]);
        $record = WinLossRecord::create([
            'opportunity_id' => $opp->id,
            'outcome' => 'won',
            'deal_value' => 12345.6789,
            'recorded_at' => now(),
        ]);

        expect($record->deal_value)->toBeString(); // decimal cast returns string
    });

    test('recorded_at is cast to datetime', function () {
        $pipeline = Pipeline::factory()->create();
        $opp = Opportunity::factory()->create(['pipeline_id' => $pipeline->id]);
        $record = WinLossRecord::create([
            'opportunity_id' => $opp->id,
            'outcome' => 'lost',
            'deal_value' => 5000,
            'recorded_at' => now(),
        ]);

        expect($record->recorded_at)->toBeInstanceOf(Carbon::class);
    });
});

// ─── PipelineSnapshot Model ───────────────────────────────────────────────────

describe('PipelineSnapshot Model', function () {
    test('has correct table name #2', function () {
        $pipeline = Pipeline::factory()->create();
        $snap = PipelineSnapshot::create([
            'pipeline_id' => $pipeline->id,
            'snapshot_date' => now()->toDateString(),
            'total_value' => 10000,
            'deal_count' => 5,
            'avg_deal_size' => 2000,
            'stage_data' => [],
        ]);

        expect($snap->getTable())->toBe('crm_pipeline_snapshots');
    });

    test('getStageData returns array', function () {
        $snap = new PipelineSnapshot([
            'stage_data' => [['stage' => 'prospecting', 'count' => 3, 'value' => 30000]],
        ]);

        $data = $snap->getStageData();
        expect($data)->toBeArray();
        expect($data[0]['stage'])->toBe('prospecting');
    });

    test('getStageData returns empty array when null', function () {
        $snap = new PipelineSnapshot;
        expect($snap->getStageData())->toBe([]);
    });

    test('snapshot_date is cast to date', function () {
        $pipeline = Pipeline::factory()->create();
        $snap = PipelineSnapshot::create([
            'pipeline_id' => $pipeline->id,
            'snapshot_date' => now()->toDateString(),
            'total_value' => 0,
            'deal_count' => 0,
            'avg_deal_size' => 0,
            'stage_data' => [],
        ]);

        expect($snap->snapshot_date)->toBeInstanceOf(Carbon::class);
    });

    test('stage_data is cast to array', function () {
        $pipeline = Pipeline::factory()->create();
        $snap = PipelineSnapshot::create([
            'pipeline_id' => $pipeline->id,
            'snapshot_date' => now()->toDateString(),
            'total_value' => 0,
            'deal_count' => 0,
            'avg_deal_size' => 0,
            'stage_data' => [['stage' => 'qualification', 'count' => 2, 'value' => 20000]],
        ]);

        expect($snap->stage_data)->toBeArray();
    });
});

// ─── PipelineAnalyticsService ─────────────────────────────────────────────────

describe('PipelineAnalyticsService', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(PipelineAnalyticsService::class);
        $this->pipeline = Pipeline::factory()->create([
            'stages' => ['prospecting', 'qualification', 'proposal', 'negotiation', 'closed_won'],
        ]);
    });

    test('recordWin creates a WinLossRecord with outcome won', function () {
        $opp = Opportunity::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'amount' => 50000,
            'status' => 'open',
        ]);

        $record = $this->service->recordWin($opp->id);

        expect($record)->toBeInstanceOf(WinLossRecord::class);
        expect($record->outcome)->toBe('won');
        expect($record->opportunity_id)->toBe($opp->id);
    });

    test('recordWin updates opportunity status to closed_won', function () {
        $opp = Opportunity::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'open',
        ]);

        $this->service->recordWin($opp->id);

        $updated = DB::table('crm_opportunities')->where('id', $opp->id)->first();
        expect($updated->status)->toBe('closed_won');
    });

    test('recordLoss creates a WinLossRecord with outcome lost', function () {
        $opp = Opportunity::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'amount' => 30000,
            'status' => 'open',
        ]);

        $record = $this->service->recordLoss($opp->id, ['reason' => 'Price too high', 'competitor' => 'Rival Co']);

        expect($record)->toBeInstanceOf(WinLossRecord::class);
        expect($record->outcome)->toBe('lost');
        expect($record->reason)->toBe('Price too high');
        expect($record->competitor)->toBe('Rival Co');
    });

    test('recordLoss updates opportunity status to closed_lost', function () {
        $opp = Opportunity::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'status' => 'open',
        ]);

        $this->service->recordLoss($opp->id);

        $updated = DB::table('crm_opportunities')->where('id', $opp->id)->first();
        expect($updated->status)->toBe('closed_lost');
    });

    test('getWinRate returns 0 when no records', function () {
        expect($this->service->getWinRate())->toBe(0.0);
    });

    test('getWinRate calculates correctly', function () {
        $opp1 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);
        $opp2 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);
        $opp3 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);
        $opp4 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);

        $this->service->recordWin($opp1->id);
        $this->service->recordWin($opp2->id);
        $this->service->recordLoss($opp3->id);
        $this->service->recordLoss($opp4->id);

        // 2 won out of 4 = 50%
        expect($this->service->getWinRate())->toBe(50.0);
    });

    test('getWinRate filters by date range', function () {
        $opp1 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);
        $opp2 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);

        $this->service->recordWin($opp1->id);
        $this->service->recordLoss($opp2->id);

        $from = now()->subDay();
        $to = now()->addDay();

        $rate = $this->service->getWinRate($from, $to);
        expect($rate)->toBe(50.0);
    });

    test('getConversionFunnel returns empty array when no opportunities', function () {
        $result = $this->service->getConversionFunnel($this->pipeline->id);
        expect($result)->toBe([]);
    });

    test('getConversionFunnel returns stage data with conversion rates', function () {
        Opportunity::factory()->count(4)->create([
            'pipeline_id' => $this->pipeline->id,
            'stage' => 'prospecting',
        ]);
        Opportunity::factory()->count(2)->create([
            'pipeline_id' => $this->pipeline->id,
            'stage' => 'qualification',
        ]);

        $funnel = $this->service->getConversionFunnel($this->pipeline->id);

        expect($funnel)->toBeArray();
        expect(count($funnel))->toBe(2);

        $prospecting = collect($funnel)->firstWhere('stage', 'prospecting');
        expect($prospecting['count'])->toBe(4);
        expect($prospecting['conversion_rate'])->toBe(100.0);

        $qualification = collect($funnel)->firstWhere('stage', 'qualification');
        expect($qualification['count'])->toBe(2);
        expect($qualification['conversion_rate'])->toBe(50.0);
    });

    test('getSalesVelocity returns 0 when no won deals', function () {
        expect($this->service->getSalesVelocity())->toBe(0.0);
    });

    test('getSalesVelocity returns a float', function () {
        $opp = Opportunity::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'amount' => 50000,
        ]);
        $this->service->recordWin($opp->id);

        // May be 0 if sales_cycle_days is 0, but should be float
        $velocity = $this->service->getSalesVelocity();
        expect($velocity)->toBeFloat();
    });

    test('getStageDistribution returns empty array when no opportunities', function () {
        $result = $this->service->getStageDistribution($this->pipeline->id);
        expect($result)->toBe([]);
    });

    test('getStageDistribution returns correct stage data', function () {
        Opportunity::factory()->count(3)->create([
            'pipeline_id' => $this->pipeline->id,
            'stage' => 'prospecting',
            'amount' => 10000,
        ]);
        Opportunity::factory()->count(1)->create([
            'pipeline_id' => $this->pipeline->id,
            'stage' => 'proposal',
            'amount' => 20000,
        ]);

        $distribution = $this->service->getStageDistribution($this->pipeline->id);

        expect($distribution)->toBeArray();
        expect(count($distribution))->toBe(2);

        $prospecting = collect($distribution)->firstWhere('stage', 'prospecting');
        expect($prospecting['count'])->toBe(3);
        expect($prospecting['percentage'])->toBe(75.0);
    });

    test('takeSnapshot creates a PipelineSnapshot', function () {
        Opportunity::factory()->count(2)->create([
            'pipeline_id' => $this->pipeline->id,
            'stage' => 'prospecting',
            'amount' => 5000,
            'status' => 'open',
        ]);

        $snapshot = $this->service->takeSnapshot($this->pipeline->id);

        expect($snapshot)->toBeInstanceOf(PipelineSnapshot::class);
        expect($snapshot->pipeline_id)->toBe($this->pipeline->id);
        expect($snapshot->deal_count)->toBeGreaterThanOrEqual(0);
    });

    test('getPipelineTrend returns array', function () {
        $trend = $this->service->getPipelineTrend($this->pipeline->id);
        expect($trend)->toBeArray();
    });

    test('getPipelineTrend includes created snapshots', function () {
        $this->service->takeSnapshot($this->pipeline->id);

        $trend = $this->service->getPipelineTrend($this->pipeline->id, 1);
        expect(count($trend))->toBeGreaterThanOrEqual(1);
        expect($trend[0])->toHaveKeys(['date', 'total_value', 'deal_count']);
    });

    test('getTopPerformers returns array', function () {
        $result = $this->service->getTopPerformers();
        expect($result)->toBeArray();
    });

    test('getTopPerformers returns user data for won deals', function () {
        $opp = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);

        WinLossRecord::create([
            'opportunity_id' => $opp->id,
            'outcome' => 'won',
            'deal_value' => 50000,
            'recorded_by' => $this->user->id,
            'recorded_at' => now(),
        ]);

        $performers = $this->service->getTopPerformers();
        expect($performers)->toBeArray();
        expect(count($performers))->toBeGreaterThanOrEqual(1);
        expect($performers[0])->toHaveKeys(['user_id', 'name', 'won_count', 'total_value']);
    });

    test('getWinLossReasons returns empty array when no records', function () {
        $result = $this->service->getWinLossReasons('lost');
        expect($result)->toBe([]);
    });

    test('getWinLossReasons returns breakdown by reason', function () {
        $opp1 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);
        $opp2 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);
        $opp3 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);

        $this->service->recordLoss($opp1->id, ['reason' => 'Price']);
        $this->service->recordLoss($opp2->id, ['reason' => 'Price']);
        $this->service->recordLoss($opp3->id, ['reason' => 'Features']);

        $reasons = $this->service->getWinLossReasons('lost');
        expect($reasons)->toBeArray();
        expect(count($reasons))->toBe(2);

        $price = collect($reasons)->firstWhere('reason', 'Price');
        expect($price['count'])->toBe(2);
        expect($price['percentage'])->toBe(66.67);
    });

    test('getAvgSalesCycle returns 0 when no won deals', function () {
        expect($this->service->getAvgSalesCycle())->toBe(0.0);
    });

    test('getAvgSalesCycle returns average days', function () {
        $pipeline = $this->pipeline;
        $opp1 = Opportunity::factory()->create(['pipeline_id' => $pipeline->id]);
        $opp2 = Opportunity::factory()->create(['pipeline_id' => $pipeline->id]);

        WinLossRecord::create([
            'opportunity_id' => $opp1->id,
            'outcome' => 'won',
            'deal_value' => 10000,
            'sales_cycle_days' => 30,
            'recorded_at' => now(),
        ]);
        WinLossRecord::create([
            'opportunity_id' => $opp2->id,
            'outcome' => 'won',
            'deal_value' => 20000,
            'sales_cycle_days' => 60,
            'recorded_at' => now(),
        ]);

        expect($this->service->getAvgSalesCycle())->toBe(45.0);
    });

    test('getDashboard returns all required keys', function () {
        $dashboard = $this->service->getDashboard();

        expect($dashboard)->toHaveKeys([
            'win_rate',
            'total_pipeline_value',
            'avg_deal_size',
            'sales_velocity',
            'avg_sales_cycle_days',
            'open_deals',
        ]);
    });

    test('getDashboard win_rate is between 0 and 100', function () {
        $opp1 = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id]);
        $this->service->recordWin($opp1->id);

        $dashboard = $this->service->getDashboard();
        expect($dashboard['win_rate'])->toBeGreaterThanOrEqual(0.0);
        expect($dashboard['win_rate'])->toBeLessThanOrEqual(100.0);
    });
});

// ─── API Endpoints ────────────────────────────────────────────────────────────

describe('Pipeline Analytics API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->pipeline = Pipeline::factory()->create();
    });

    test('GET /api/v1/crm/pipeline-analytics/dashboard returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'win_rate',
                'total_pipeline_value',
                'avg_deal_size',
                'sales_velocity',
                'avg_sales_cycle_days',
                'open_deals',
            ]);
    });

    test('GET /api/v1/crm/pipeline-analytics/win-rate returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/win-rate')
            ->assertOk()
            ->assertJsonStructure(['win_rate']);
    });

    test('POST /api/v1/crm/pipeline-analytics/record-win returns 201', function () {
        $opp = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id, 'status' => 'open']);

        $this->postJson('/api/v1/crm/pipeline-analytics/record-win', [
            'opportunity_id' => $opp->id,
            'reason' => 'Best product',
        ])->assertCreated();

        $this->assertDatabaseHas('crm_win_loss_records', [
            'opportunity_id' => $opp->id,
            'outcome' => 'won',
        ]);
    });

    test('POST /api/v1/crm/pipeline-analytics/record-win requires opportunity_id', function () {
        $this->postJson('/api/v1/crm/pipeline-analytics/record-win', [])
            ->assertUnprocessable();
    });

    test('POST /api/v1/crm/pipeline-analytics/record-loss returns 201', function () {
        $opp = Opportunity::factory()->create(['pipeline_id' => $this->pipeline->id, 'status' => 'open']);

        $this->postJson('/api/v1/crm/pipeline-analytics/record-loss', [
            'opportunity_id' => $opp->id,
            'reason' => 'Price too high',
            'competitor' => 'Rival Corp',
        ])->assertCreated();

        $this->assertDatabaseHas('crm_win_loss_records', [
            'opportunity_id' => $opp->id,
            'outcome' => 'lost',
        ]);
    });

    test('POST /api/v1/crm/pipeline-analytics/record-loss requires opportunity_id', function () {
        $this->postJson('/api/v1/crm/pipeline-analytics/record-loss', [])
            ->assertUnprocessable();
    });

    test('GET /api/v1/crm/pipeline-analytics/conversion-funnel returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/conversion-funnel?pipeline_id='.$this->pipeline->id)
            ->assertOk();
    });

    test('GET /api/v1/crm/pipeline-analytics/sales-velocity returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/sales-velocity')
            ->assertOk()
            ->assertJsonStructure(['sales_velocity']);
    });

    test('GET /api/v1/crm/pipeline-analytics/stage-distribution returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/stage-distribution?pipeline_id='.$this->pipeline->id)
            ->assertOk();
    });

    test('POST /api/v1/crm/pipeline-analytics/snapshots creates snapshot', function () {
        $this->postJson('/api/v1/crm/pipeline-analytics/snapshots', [
            'pipeline_id' => $this->pipeline->id,
        ])->assertCreated();

        $this->assertDatabaseHas('crm_pipeline_snapshots', [
            'pipeline_id' => $this->pipeline->id,
        ]);
    });

    test('POST /api/v1/crm/pipeline-analytics/snapshots requires pipeline_id', function () {
        $this->postJson('/api/v1/crm/pipeline-analytics/snapshots', [])
            ->assertUnprocessable();
    });

    test('GET /api/v1/crm/pipeline-analytics/trend returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/trend?pipeline_id='.$this->pipeline->id.'&days=30')
            ->assertOk();
    });

    test('GET /api/v1/crm/pipeline-analytics/top-performers returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/top-performers')
            ->assertOk();
    });

    test('GET /api/v1/crm/pipeline-analytics/win-loss-reasons returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/win-loss-reasons?outcome=lost')
            ->assertOk();
    });

    test('GET /api/v1/crm/pipeline-analytics/avg-sales-cycle returns 200', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/avg-sales-cycle')
            ->assertOk()
            ->assertJsonStructure(['avg_sales_cycle_days']);
    });
});

describe('Pipeline Analytics API unauthenticated', function () {
    test('unauthenticated request returns 401', function () {
        $this->getJson('/api/v1/crm/pipeline-analytics/dashboard')
            ->assertUnauthorized();
    });
});
