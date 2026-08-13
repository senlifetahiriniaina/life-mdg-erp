<?php

declare(strict_types=1);

use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityScore;
use Modules\CRM\Models\Territory;
use Modules\CRM\Services\TerritoryForecastService;


// ─── Territory Model Tests ────────────────────────────────────────────────────

describe('Territory Model', function () {
    test('territory has correct table name', function () {
        $territory = Territory::factory()->create();
        expect($territory->getTable())->toBe('crm_territories');
    });

    test('territory can have parent territory', function () {
        $parent = Territory::factory()->create();
        $child = Territory::factory()->create(['parent_territory_id' => $parent->id]);

        expect($child->parent()->first()->id)->toBe($parent->id);
    });

    test('territory can have child territories', function () {
        $parent = Territory::factory()->create();
        Territory::factory()->count(3)->create(['parent_territory_id' => $parent->id]);

        expect($parent->children()->count())->toBe(3);
    });

    test('ytdRevenue calculates correctly for closed_won opportunities', function () {
        $territory = Territory::factory()->create(['sales_target' => 500000, 'year_start_date' => '2026-01-01']);

        // Create opportunities in different statuses
        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 100000,
            'closed_at' => now(),
        ]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 50000,
            'closed_at' => now(),
        ]);

        // This should not be counted (closed_lost)
        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_lost',
            'amount' => 200000,
            'closed_at' => now(),
        ]);

        // This should not be counted (closed_at before year start)
        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 75000,
            'closed_at' => now()->subYear(),
        ]);

        expect($territory->ytdRevenue())->toBe(150000.0);
    });

    test('ytdRevenue uses Jan 1 of current year when year_start_date is null', function () {
        $territory = Territory::factory()->create(['sales_target' => 500000, 'year_start_date' => null]);

        $closedBefore = now()->startOfYear()->subDay();
        $closedAfter = now()->startOfYear()->addDay();

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 100000,
            'closed_at' => $closedBefore,
        ]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 50000,
            'closed_at' => $closedAfter,
        ]);

        expect($territory->ytdRevenue())->toBe(50000.0);
    });

    test('quotaAttainment calculates percentage correctly', function () {
        $territory = Territory::factory()->create(['sales_target' => 500000, 'year_start_date' => '2026-01-01']);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 100000,
            'closed_at' => now(),
        ]);

        expect($territory->quotaAttainment())->toBe(20.0); // 100000 / 500000 * 100
    });

    test('quotaAttainment returns 0 when sales_target is 0', function () {
        $territory = Territory::factory()->create(['sales_target' => 0]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 100000,
            'closed_at' => now(),
        ]);

        expect($territory->quotaAttainment())->toBe(0.0);
    });

    test('forecastedRevenue sums opportunities weighted by win_probability', function () {
        $territory = Territory::factory()->create(['sales_target' => 500000, 'year_start_date' => '2026-01-01']);

        $opp1 = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
            'stage' => 'proposal',
            'amount' => 100000,
            'probability' => 50,
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $opp1->id,
            'win_probability' => 0.5,
        ]);

        $opp2 = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
            'stage' => 'negotiation',
            'amount' => 200000,
            'probability' => 75,
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $opp2->id,
            'win_probability' => 0.75,
        ]);

        // Closed won should also be included
        $opp3 = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'stage' => 'closed_won',
            'amount' => 50000,
            'probability' => 100,
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $opp3->id,
            'win_probability' => 1.0,
        ]);

        $expected = (100000 * 0.5) + (200000 * 0.75) + (50000 * 1.0);
        expect($territory->forecastedRevenue())->toBe($expected);
    });

    test('quotaForecast calculates percentage of forecasted revenue', function () {
        $territory = Territory::factory()->create(['sales_target' => 500000, 'year_start_date' => '2026-01-01']);

        $opp = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
            'stage' => 'proposal',
            'amount' => 200000,
            'probability' => 50,
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $opp->id,
            'win_probability' => 0.5,
        ]);

        // Expected: 200000 * 0.5 = 100000
        // Forecast %: 100000 / 500000 * 100 = 20
        expect($territory->quotaForecast())->toBe(20.0);
    });

    test('opportunitiesInPipeline returns only non-lost opportunities', function () {
        $territory = Territory::factory()->create();

        $won = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
        ]);

        $open = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
        ]);

        $lost = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_lost',
        ]);

        $pipeline = $territory->opportunitiesInPipeline();
        expect($pipeline->count())->toBe(2);
        expect($pipeline->pluck('id'))->toContain($won->id, $open->id);
        expect($pipeline->pluck('id'))->not->toContain($lost->id);
    });
});

// ─── Territory API Tests ──────────────────────────────────────────────────────

describe('Territory API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('authenticated user can list territories', function () {
        Territory::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/territories')
            ->assertOk();

        expect($response->json('data'))->toHaveCount(5);
        // Verify response has pagination-like structure
        expect($response->json())->toHaveKey('data');
    });

    test('territory list can be filtered by active status', function () {
        Territory::factory()->count(3)->create(['is_active' => true]);
        Territory::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/territories?is_active=true')
            ->assertOk();

        expect($response->json('data'))->toHaveCount(3);
    });

    test('territory list can be searched by name', function () {
        Territory::factory()->create(['name' => 'North Territory', 'code' => 'NT']);
        Territory::factory()->create(['name' => 'South Territory', 'code' => 'ST']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/territories?search=North')
            ->assertOk();

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('North Territory');
    });

    test('territory list can be searched by code', function () {
        Territory::factory()->create(['name' => 'North Territory', 'code' => 'NT']);
        Territory::factory()->create(['name' => 'South Territory', 'code' => 'ST']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/territories?search=ST')
            ->assertOk();

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.code'))->toBe('ST');
    });

    test('authenticated user can create a territory', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/crm/territories', [
                'name' => 'West Territory',
                'code' => 'WT',
                'assigned_to' => $this->user->id,
                'sales_target' => 500000,
                'currency' => 'USD',
                'region' => 'West Coast',
            ])
            ->assertStatus(201);

        expect($response->json('name'))->toBe('West Territory');
        expect($response->json('code'))->toBe('WT');

        $this->assertDatabaseHas('crm_territories', [
            'name' => 'West Territory',
            'code' => 'WT',
        ]);
    });

    test('territory creation requires name and code', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/crm/territories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'assigned_to', 'sales_target']);
    });

    test('territory code must be unique', function () {
        Territory::factory()->create(['code' => 'WT']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/crm/territories', [
                'name' => 'Another Territory',
                'code' => 'WT',
                'assigned_to' => $this->user->id,
                'sales_target' => 300000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    test('authenticated user can view a territory', function () {
        $territory = Territory::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/crm/territories/{$territory->id}")
            ->assertOk();

        expect($response->json('id'))->toBe($territory->id);
        expect($response->json('name'))->toBe($territory->name);
        expect($response->json('code'))->toBe($territory->code);
        expect($response->json())->toHaveKey('ytd_revenue');
        expect($response->json())->toHaveKey('quota_attainment');
        expect($response->json())->toHaveKey('forecast_revenue');
    });

    test('authenticated user can update a territory', function () {
        $territory = Territory::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/crm/territories/{$territory->id}", [
                'name' => 'New Name',
            ])
            ->assertOk();

        expect($response->json('name'))->toBe('New Name');

        $this->assertDatabaseHas('crm_territories', [
            'id' => $territory->id,
            'name' => 'New Name',
        ]);
    });

    test('authenticated user can delete a territory without dependencies', function () {
        $territory = Territory::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/crm/territories/{$territory->id}")
            ->assertOk();

        $this->assertDatabaseMissing('crm_territories', ['id' => $territory->id]);
    });

    test('cannot delete territory with child territories', function () {
        $parent = Territory::factory()->create();
        Territory::factory()->create(['parent_territory_id' => $parent->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/crm/territories/{$parent->id}")
            ->assertStatus(409);
    });

    test('cannot delete territory with opportunities', function () {
        $territory = Territory::factory()->create();
        Opportunity::factory()->create(['territory_id' => $territory->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/crm/territories/{$territory->id}")
            ->assertStatus(409);
    });

    test('authenticated user can view territory forecast', function () {
        $territory = Territory::factory()->create();
        Opportunity::factory()->count(3)->create(['territory_id' => $territory->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/crm/territories/{$territory->id}/forecast")
            ->assertOk();

        expect($response->json())->toHaveKey('territory');
        expect($response->json())->toHaveKey('stages');
        expect($response->json())->toHaveKey('timeline');
        expect($response->json())->toHaveKey('at_risk_count');
    });

    test('forecast returns pipeline breakdown by stage', function () {
        $territory = Territory::factory()->create();

        Opportunity::factory()->count(2)->create([
            'territory_id' => $territory->id,
            'stage' => 'prospecting',
            'amount' => 50000,
        ]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'stage' => 'proposal',
            'amount' => 100000,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/crm/territories/{$territory->id}/forecast")
            ->assertOk();

        $stages = $response->json('stages');
        expect($stages['prospecting']['count'])->toBe(2);
        expect($stages['proposal']['count'])->toBe(1);
    });

    test('authenticated user can view at-risk opportunities', function () {
        $territory = Territory::factory()->create();

        // Create low-score opportunity (at risk)
        $atRisk = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $atRisk->id,
            'total_score' => 30,
        ]);

        // Create high-score opportunity (not at risk)
        $notAtRisk = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $notAtRisk->id,
            'total_score' => 80,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/crm/territories/{$territory->id}/at-risk")
            ->assertOk();

        expect($response->json('data'))->toHaveCount(1);
    });

    test('at-risk includes aged opportunities without recent updates', function () {
        $territory = Territory::factory()->create();

        // Create old opportunity (90+ days without update)
        $aged = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
            'updated_at' => now()->subDays(100),
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $aged->id,
            'total_score' => 60, // Not low score
        ]);

        // Create recent opportunity
        $recent = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
            'updated_at' => now(),
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $recent->id,
            'total_score' => 60,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/crm/territories/{$territory->id}/at-risk")
            ->assertOk();

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.id'))->toBe($aged->id);
    });

    test('authenticated user can assign opportunity to territory', function () {
        $territory = Territory::factory()->create();
        $opportunity = Opportunity::factory()->create(['territory_id' => null]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/crm/territories/{$territory->id}/assign-opportunity", [
                'opportunity_id' => $opportunity->id,
            ])
            ->assertOk();

        expect($response->json('territory_id'))->toBe($territory->id);

        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $opportunity->id,
            'territory_id' => $territory->id,
        ]);
    });

    test('assign-opportunity requires valid opportunity_id', function () {
        $territory = Territory::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/crm/territories/{$territory->id}/assign-opportunity", [
                'opportunity_id' => 99999,
            ])
            ->assertUnprocessable();
    });

    test('authenticated user can view all territories forecast', function () {
        Territory::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/forecast/by-territory')
            ->assertOk();

        expect($response->json())->toHaveKey('territories');
        expect($response->json())->toHaveKey('summary');
        expect($response->json('territories'))->toHaveCount(3);
    });

    test('forecast by territory includes summary', function () {
        $t1 = Territory::factory()->create(['sales_target' => 500000]);
        $t2 = Territory::factory()->create(['sales_target' => 300000]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/forecast/by-territory')
            ->assertOk();

        $summary = $response->json('summary');
        expect($summary['total_target'])->toBe(800000);
    });

    test('authenticated user can view forecast comparison', function () {
        $territory = Territory::factory()->create(['sales_target' => 500000]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 100000,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/forecast/territory-comparison?territory_id='.$territory->id)
            ->assertOk();

        expect($response->json('territory_id'))->toBe($territory->id);
        expect($response->json())->toHaveKey('ytd_revenue');
        expect($response->json())->toHaveKey('sales_target');
        expect($response->json())->toHaveKey('forecast_revenue');
        expect($response->json())->toHaveKey('variance');
        expect($response->json())->toHaveKey('status');
    });

    test('forecast comparison requires territory_id', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/forecast/territory-comparison')
            ->assertUnprocessable();
    });

    test('forecast comparison status is on_track when forecast >= target', function () {
        $territory = Territory::factory()->create(['sales_target' => 100000]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
            'stage' => 'proposal',
            'amount' => 150000,
            'probability' => 100,
        ]);
        $opp = Opportunity::orderByDesc('id')->first();
        OpportunityScore::factory()->create([
            'opportunity_id' => $opp->id,
            'win_probability' => 1.0,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/forecast/territory-comparison?territory_id='.$territory->id)
            ->assertOk();

        expect($response->json('status'))->toBe('on_track');
    });

    test('forecast comparison status is at_risk when forecast 75-99% of target', function () {
        $territory = Territory::factory()->create(['sales_target' => 100000]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
            'stage' => 'proposal',
            'amount' => 100000,
            'probability' => 75,
        ]);
        $opp = Opportunity::orderByDesc('id')->first();
        OpportunityScore::factory()->create([
            'opportunity_id' => $opp->id,
            'win_probability' => 0.75,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/forecast/territory-comparison?territory_id='.$territory->id)
            ->assertOk();

        expect($response->json('status'))->toBe('at_risk');
    });

    test('forecast comparison status is at_serious_risk when forecast < 75% of target', function () {
        $territory = Territory::factory()->create(['sales_target' => 100000]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
            'stage' => 'proposal',
            'amount' => 100000,
            'probability' => 50,
        ]);
        $opp = Opportunity::orderByDesc('id')->first();
        OpportunityScore::factory()->create([
            'opportunity_id' => $opp->id,
            'win_probability' => 0.5,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/crm/forecast/territory-comparison?territory_id='.$territory->id)
            ->assertOk();

        expect($response->json('status'))->toBe('at_serious_risk');
    });
});

// ─── Territory Service Tests ──────────────────────────────────────────────────

describe('TerritoryForecastService', function () {
    beforeEach(function () {
        $this->service = app(TerritoryForecastService::class);
    });

    test('territoryForecast returns correct structure', function () {
        Territory::factory()->count(2)->create();

        $forecast = $this->service->territoryForecast();

        expect($forecast)->toHaveKey('territories');
        expect($forecast)->toHaveKey('summary');
        expect($forecast['territories'])->toHaveCount(2);
    });

    test('territoryDetail returns detailed forecast for single territory', function () {
        $territory = Territory::factory()->create();
        Opportunity::factory()->count(3)->create(['territory_id' => $territory->id]);

        $detail = $this->service->territoryDetail($territory);

        expect($detail)->toHaveKey('territory');
        expect($detail)->toHaveKey('stages');
        expect($detail)->toHaveKey('timeline');
        expect($detail)->toHaveKey('at_risk_count');
    });

    test('atRiskOpportunities filters by low score', function () {
        $territory = Territory::factory()->create();

        $atRisk = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $atRisk->id,
            'total_score' => 30,
        ]);

        $safe = Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'open',
        ]);
        OpportunityScore::factory()->create([
            'opportunity_id' => $safe->id,
            'total_score' => 80,
        ]);

        $atRiskOpps = $this->service->atRiskOpportunities($territory);

        expect($atRiskOpps->count())->toBe(1);
        expect($atRiskOpps->first()->id)->toBe($atRisk->id);
    });

    test('atRiskOpportunities excludes closed opportunities', function () {
        $territory = Territory::factory()->create();

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
        ]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_lost',
        ]);

        $atRiskOpps = $this->service->atRiskOpportunities($territory);

        expect($atRiskOpps->count())->toBe(0);
    });

    test('assignOpportunity updates opportunity territory_id', function () {
        $territory = Territory::factory()->create();
        $opportunity = Opportunity::factory()->create(['territory_id' => null]);

        $result = $this->service->assignOpportunity($opportunity, $territory);

        expect($result->territory_id)->toBe($territory->id);
    });

    test('forecastVsTarget returns comparison data', function () {
        $territory = Territory::factory()->create(['sales_target' => 500000]);

        Opportunity::factory()->create([
            'territory_id' => $territory->id,
            'status' => 'closed_won',
            'amount' => 100000,
            'closed_at' => now(),
        ]);

        $comparison = $this->service->forecastVsTarget($territory);

        expect($comparison)->toHaveKey('territory_id');
        expect($comparison)->toHaveKey('territory_name');
        expect($comparison)->toHaveKey('ytd_revenue');
        expect($comparison)->toHaveKey('sales_target');
        expect($comparison)->toHaveKey('forecast_revenue');
        expect($comparison)->toHaveKey('variance');
        expect($comparison)->toHaveKey('variance_percent');
        expect($comparison)->toHaveKey('status');
    });

    test('forecastVsTarget calculates variance correctly', function () {
        $territory = Territory::factory()->create(['sales_target' => 500000]);

        $comparison = $this->service->forecastVsTarget($territory);

        expect($comparison['variance'])->toBe((float) ($comparison['forecast_revenue'] - $comparison['sales_target']));
    });
});
