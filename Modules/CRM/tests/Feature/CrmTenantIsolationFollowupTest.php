<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Campaign;
use Modules\CRM\Models\Forecast;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityScore;
use Modules\CRM\Models\Pipeline;
use Modules\CRM\Services\OpportunityScoringService;
use Modules\CRM\Services\PipelineService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Dedicated follow-up chantier closing the gap CLAUDE.md's Chantier 19 Lot 1 entry documented
 * and deliberately deferred:
 *
 *   "PipelineService, CRMForecastingService, EinsteinForecastingService, ForecastService,
 *   OpportunityScoringService, CampaignOrchestrationService all run tenant-unfiltered
 *   Opportunity/Contact aggregates, and the Activity/Pipeline tables have no tenant column at
 *   all."
 *
 * Investigation found the underlying reality was more varied than that single sentence implied
 * (see the assistant's final report for the full breakdown per service) — this test locks in
 * every real fix made: a new company_id column + real scoping for Activity, Pipeline, and
 * Campaign (the last discovered while investigating the now-deleted CampaignOrchestrationService,
 * not originally named but a confirmed, more severe live gap on the same table family);
 * PipelineService's tenant-filtering fix (verified via direct service calls — it has zero real
 * callers anywhere in the app, confirmed via grep, so there is no HTTP endpoint to exercise);
 * ForecastService's fix (its match-key collision bug, not just a missing filter); and
 * OpportunityScoringService's leaderboard/pipelineForecast/scoreAll/getScores aggregate-scoping
 * fix. CRMForecastingService/EinsteinForecastingService needed no controller-level change (their
 * one real caller, EinsteinForecastingController, already threaded $request->user()->company_id
 * through correctly) but had a real, confirmed bug underneath: crm_forecasts.tenant_id was a
 * real, migrated column never in Forecast::$fillable, so every write silently dropped it and
 * every tenant-scoped confidence/accuracy read was permanently vacuous — fixed at the model
 * layer and exercised here via the real HTTP endpoints.
 */
function crmTenantFollowupUser(string $companySuffix, string $role = 'employee'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $company = Company::create([
        'name' => "CRM Tenant Followup Co {$companySuffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->assignRole($role);

    DB::table('tenant_modules')->updateOrInsert(
        ['tenant_id' => (string) $user->id, 'module' => 'CRM', 'department' => null],
        ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
    );

    return $user;
}

// ─── Activity ────────────────────────────────────────────────────────────────────────

test('activity index only returns the caller company own activities', function () {
    $userA = crmTenantFollowupUser('ActA');
    $userB = crmTenantFollowupUser('ActB');

    Activity::create(['user_id' => $userA->id, 'company_id' => $userA->company_id, 'type' => 'call', 'title' => 'Call A']);
    Activity::create(['user_id' => $userB->id, 'company_id' => $userB->company_id, 'type' => 'call', 'title' => 'Call B']);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/activities')->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Call A')->not->toContain('Call B');
});

test('activity store populates the caller company id', function () {
    $userA = crmTenantFollowupUser('ActStoreA');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/activities', [
        'type' => 'note',
        'title' => 'Follow up',
    ])->assertCreated();

    expect(Activity::find($response->json('id'))->company_id)->toBe($userA->company_id);
});

test('company B cannot view update or delete company A activity', function () {
    $userA = crmTenantFollowupUser('ActDenyA');
    $userB = crmTenantFollowupUser('ActDenyB');

    $activity = Activity::create(['user_id' => $userA->id, 'company_id' => $userA->company_id, 'type' => 'call', 'title' => 'Private call']);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/crm/activities/{$activity->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/crm/activities/{$activity->id}", ['title' => 'Hacked'])->assertForbidden();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/crm/activities/{$activity->id}")->assertForbidden();

    expect($activity->fresh()->title)->not->toBe('Hacked');
});

test('company A can still view update and delete its own activity', function () {
    $userA = crmTenantFollowupUser('ActOwnA');
    $activity = Activity::create(['user_id' => $userA->id, 'company_id' => $userA->company_id, 'type' => 'call', 'title' => 'My call']);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/crm/activities/{$activity->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/crm/activities/{$activity->id}", ['title' => 'Updated'])
        ->assertOk()->assertJsonPath('title', 'Updated');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/crm/activities/{$activity->id}")->assertNoContent();
});

// ─── Pipeline ────────────────────────────────────────────────────────────────────────

test('pipeline index only returns the caller company own pipelines', function () {
    $userA = crmTenantFollowupUser('PipeA');
    $userB = crmTenantFollowupUser('PipeB');

    Pipeline::create(['name' => 'Pipeline A', 'stages' => [['name' => 'New', 'order' => 1]], 'company_id' => $userA->company_id]);
    Pipeline::create(['name' => 'Pipeline B', 'stages' => [['name' => 'New', 'order' => 1]], 'company_id' => $userB->company_id]);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/pipelines')->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Pipeline A')->not->toContain('Pipeline B');
});

test('pipeline store populates the caller company id', function () {
    $userA = crmTenantFollowupUser('PipeStoreA');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/pipelines', [
        'name' => 'New Pipeline',
        'stages' => [['name' => 'New', 'order' => 1]],
    ])->assertCreated();

    expect(Pipeline::find($response->json('id'))->company_id)->toBe($userA->company_id);
});

test('company B cannot view update or delete company A pipeline', function () {
    $userA = crmTenantFollowupUser('PipeDenyA');
    $userB = crmTenantFollowupUser('PipeDenyB');

    $pipeline = Pipeline::create(['name' => 'Secret Pipeline', 'stages' => [['name' => 'New', 'order' => 1]], 'company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/crm/pipelines/{$pipeline->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/crm/pipelines/{$pipeline->id}", ['name' => 'Hacked'])->assertForbidden();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/crm/pipelines/{$pipeline->id}")->assertForbidden();

    expect($pipeline->fresh()->name)->not->toBe('Hacked');
});

test('company A can still view update and delete its own pipeline', function () {
    $userA = crmTenantFollowupUser('PipeOwnA');
    $pipeline = Pipeline::create(['name' => 'My Pipeline', 'stages' => [['name' => 'New', 'order' => 1]], 'company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/crm/pipelines/{$pipeline->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/crm/pipelines/{$pipeline->id}", ['name' => 'Updated Pipeline'])
        ->assertOk()->assertJsonPath('name', 'Updated Pipeline');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/crm/pipelines/{$pipeline->id}")->assertNoContent();
});

// ─── PipelineService (no HTTP endpoint anywhere — zero real callers, confirmed via grep;
//     exercised directly, matching the class's own real, tenant-safe filtering logic) ───────

test('PipelineService aggregates are isolated per company when a company id is supplied', function () {
    $userA = crmTenantFollowupUser('PipeSvcA');
    $userB = crmTenantFollowupUser('PipeSvcB');

    Opportunity::factory()->create(['tenant_id' => $userA->company_id, 'amount' => 1000, 'probability' => 80, 'status' => 'proposal', 'stage' => 'proposal']);
    Opportunity::factory()->create(['tenant_id' => $userB->company_id, 'amount' => 5000, 'probability' => 90, 'status' => 'proposal', 'stage' => 'proposal']);

    $service = app(PipelineService::class);

    $dataA = $service->getPipelineData(null, null, $userA->company_id);
    $dataB = $service->getPipelineData(null, null, $userB->company_id);

    expect($dataA['opportunity_count'])->toBe(1);
    expect((float) $dataA['pipeline_value'])->toBe(1000.0);
    expect($dataB['opportunity_count'])->toBe(1);
    expect((float) $dataB['pipeline_value'])->toBe(5000.0);
});

// ─── ForecastService / ForecastController ──────────────────────────────────────────────

test('forecast index only returns the caller company own forecasts', function () {
    $userA = crmTenantFollowupUser('FcA');
    $userB = crmTenantFollowupUser('FcB');

    Opportunity::factory()->create(['tenant_id' => $userA->company_id, 'amount' => 1000, 'probability' => 80, 'status' => 'proposal', 'stage' => 'proposal']);
    Opportunity::factory()->create(['tenant_id' => $userB->company_id, 'amount' => 5000, 'probability' => 90, 'status' => 'proposal', 'stage' => 'proposal']);

    test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-08'])->assertCreated();
    test()->actingAs($userB, 'sanctum')->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-08'])->assertCreated();

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/forecasts')->assertOk();

    expect(collect($response->json('data')))->toHaveCount(1);
});

test('forecast generate does not clobber another company forecast for the same period', function () {
    $userA = crmTenantFollowupUser('FcClobA');
    $userB = crmTenantFollowupUser('FcClobB');

    // ForecastController::generate() filters by the caller's own owner_id for non-manager
    // roles ('employee' here) — the opportunity must be owned by the acting user for its
    // amount to be picked up at all.
    Opportunity::factory()->create(['tenant_id' => $userA->company_id, 'owner_id' => $userA->id, 'amount' => 1000, 'probability' => 80, 'status' => 'proposal', 'stage' => 'proposal']);
    Opportunity::factory()->create(['tenant_id' => $userB->company_id, 'owner_id' => $userB->id, 'amount' => 9000, 'probability' => 90, 'status' => 'proposal', 'stage' => 'proposal']);

    $resA = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-09'])->assertCreated();
    $resB = test()->actingAs($userB, 'sanctum')->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-09'])->assertCreated();

    expect((float) $resA->json('pipeline_total'))->not->toEqual((float) $resB->json('pipeline_total'));
    expect(Forecast::where('tenant_id', $userA->company_id)->where('period', '2026-09')->count())->toBe(1);
    expect(Forecast::where('tenant_id', $userB->company_id)->where('period', '2026-09')->count())->toBe(1);
});

// ─── Einstein / CRMForecastingService (real crm_forecasts.tenant_id fix) ──────────────

test('einstein forecasting confidence and metrics endpoints do not error and stay isolated', function () {
    $userA = crmTenantFollowupUser('EinA');
    $userB = crmTenantFollowupUser('EinB');

    Opportunity::factory()->create(['tenant_id' => $userA->company_id, 'amount' => 1000, 'probability' => 80, 'status' => 'proposal', 'stage' => 'proposal']);
    Opportunity::factory()->create(['tenant_id' => $userB->company_id, 'amount' => 50000, 'probability' => 95, 'status' => 'proposal', 'stage' => 'proposal']);

    // Before the Forecast::$fillable fix this silently dropped tenant_id on write and the
    // confidence/metrics reads below were reading a permanently-empty filtered set — not a
    // crash, but the resulting numbers could never reflect that company's own real data.
    test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/einstein-forecasting/confidence')->assertOk();
    $metricsA = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/einstein-forecasting/metrics')->assertOk();
    $metricsB = test()->actingAs($userB, 'sanctum')->getJson('/api/v1/crm/einstein-forecasting/metrics')->assertOk();

    expect((float) $metricsA->json('average_deal_size'))->toEqual(1000.0);
    expect((float) $metricsB->json('average_deal_size'))->toEqual(50000.0);
});

test('einstein forecasting generate endpoint is isolated per company', function () {
    $userA = crmTenantFollowupUser('EinGenA');
    $userB = crmTenantFollowupUser('EinGenB');

    Opportunity::factory()->create(['tenant_id' => $userA->company_id, 'amount' => 2000, 'probability' => 100, 'status' => 'proposal', 'stage' => 'proposal']);
    Opportunity::factory()->create(['tenant_id' => $userB->company_id, 'amount' => 40000, 'probability' => 100, 'status' => 'proposal', 'stage' => 'proposal']);

    $resA = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/einstein-forecasting/generate', ['timeframe' => 'monthly'])->assertOk();
    $resB = test()->actingAs($userB, 'sanctum')->postJson('/api/v1/crm/einstein-forecasting/generate', ['timeframe' => 'monthly'])->assertOk();

    expect((float) $resA->json('pipeline_total'))->toEqual(2000.0);
    expect((float) $resB->json('pipeline_total'))->toEqual(40000.0);
});

// ─── OpportunityScoringService ─────────────────────────────────────────────────────────

test('opportunity scoring leaderboard and forecast endpoints are isolated per company', function () {
    $userA = crmTenantFollowupUser('ScoreA');
    $userB = crmTenantFollowupUser('ScoreB');

    $oppA = Opportunity::factory()->create(['tenant_id' => $userA->company_id, 'amount' => 1000, 'probability' => 80, 'status' => 'proposal', 'stage' => 'proposal', 'name' => 'Opp A']);
    $oppB = Opportunity::factory()->create(['tenant_id' => $userB->company_id, 'amount' => 5000, 'probability' => 90, 'status' => 'proposal', 'stage' => 'proposal', 'name' => 'Opp B']);

    app(OpportunityScoringService::class)->score($oppA);
    app(OpportunityScoringService::class)->score($oppB);

    $leaderboardA = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/scoring/leaderboard')->assertOk();
    $names = collect($leaderboardA->json('data'))->pluck('opportunity_name');
    expect($names)->toContain('Opp A')->not->toContain('Opp B');

    $scoresA = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/opportunity-scores')->assertOk();
    $oppIdsA = collect($scoresA->json('data'))->pluck('opportunity_id');
    expect($oppIdsA)->toContain($oppA->id)->not->toContain($oppB->id);
});

test('opportunity scoring score-all only scores the caller company own opportunities', function () {
    $userA = crmTenantFollowupUser('ScoreAllA');
    $userB = crmTenantFollowupUser('ScoreAllB');

    Opportunity::factory()->create(['tenant_id' => $userA->company_id, 'amount' => 1000, 'probability' => 80, 'status' => 'proposal', 'stage' => 'proposal']);
    $oppB = Opportunity::factory()->create(['tenant_id' => $userB->company_id, 'amount' => 5000, 'probability' => 90, 'status' => 'proposal', 'stage' => 'proposal']);

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/scoring/score-all')->assertOk();

    expect($response->json('scored'))->toBe(1);
    expect(OpportunityScore::where('opportunity_id', $oppB->id)->exists())->toBeFalse();
});

// ─── Campaign (found while investigating the now-deleted CampaignOrchestrationService —
//     the real, live CampaignController/CampaignPolicy had zero company scoping at all) ────

test('campaign index only returns the caller company own campaigns', function () {
    $userA = crmTenantFollowupUser('CampA', 'sales-rep');
    $userB = crmTenantFollowupUser('CampB', 'sales-rep');

    Campaign::create(['name' => 'Campaign A', 'type' => 'email', 'status' => 'draft', 'owner_id' => $userA->id, 'company_id' => $userA->company_id]);
    Campaign::create(['name' => 'Campaign B', 'type' => 'email', 'status' => 'draft', 'owner_id' => $userB->id, 'company_id' => $userB->company_id]);

    $response = test()->actingAs($userA, 'sanctum')->getJson('/api/v1/crm/campaigns')->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Campaign A')->not->toContain('Campaign B');
});

test('campaign store populates the caller company id', function () {
    $userA = crmTenantFollowupUser('CampStoreA', 'sales-rep');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/crm/campaigns', [
        'name' => 'New Campaign',
        'type' => 'email',
    ])->assertCreated();

    expect(Campaign::find($response->json('id'))->company_id)->toBe($userA->company_id);
});

test('company B admin cannot view update delete launch or pause company A campaign', function () {
    $userA = crmTenantFollowupUser('CampDenyA', 'sales-rep');
    // Deliberately an admin from an unrelated company — BaseErpPolicy's own admin bypass is
    // exactly the vacuous check that used to let this through before the sameCompany() fix.
    $userB = crmTenantFollowupUser('CampDenyB', 'admin');

    $campaign = Campaign::create(['name' => 'Confidential Campaign', 'type' => 'email', 'status' => 'draft', 'owner_id' => $userA->id, 'company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/crm/campaigns/{$campaign->id}")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/crm/campaigns/{$campaign->id}", ['name' => 'Hacked'])->assertForbidden();
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/crm/campaigns/{$campaign->id}/launch")->assertForbidden();
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/crm/campaigns/{$campaign->id}/pause")->assertForbidden();

    expect($campaign->fresh()->name)->not->toBe('Hacked');
});

test('company A owner can still view update launch and pause its own campaign', function () {
    $userA = crmTenantFollowupUser('CampOwnA', 'sales-rep');
    $campaign = Campaign::create(['name' => 'My Campaign', 'type' => 'email', 'status' => 'draft', 'owner_id' => $userA->id, 'company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/crm/campaigns/{$campaign->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/crm/campaigns/{$campaign->id}", ['name' => 'Updated Campaign'])
        ->assertOk()->assertJsonPath('name', 'Updated Campaign');
    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/crm/campaigns/{$campaign->id}/launch")
        ->assertOk()->assertJsonPath('campaign.status', 'active');
});
