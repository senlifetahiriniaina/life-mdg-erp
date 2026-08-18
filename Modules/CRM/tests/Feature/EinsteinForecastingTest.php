<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Opportunity;

uses(RefreshDatabase::class);

// Chantier 10: EinsteinForecastingService::analyzeConfidence()/calculateForecastMetrics() were
// unconditional ['implemented' => false, ...] stubs despite being live behind real, routed,
// RBAC-gated endpoints. This locks in the real wiring onto CRMForecastingService.

test('confidence endpoint returns real bucketed data, not a stub', function () {
    $user = actingAsUser('admin');

    Opportunity::factory()->create([
        'tenant_id' => $user->company_id ?? 0,
        'owner_id' => $user->id,
        'probability' => 85,
        'status' => 'open',
    ]);
    Opportunity::factory()->create([
        'tenant_id' => $user->company_id ?? 0,
        'owner_id' => $user->id,
        'probability' => 20,
        'status' => 'open',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/einstein-forecasting/confidence');

    $response->assertOk();
    $response->assertJsonStructure([
        'high_confidence_deals',
        'medium_confidence_deals',
        'low_confidence_deals',
        'confidence_index',
        'recommendations',
    ]);
    // Not the old stub shape.
    $response->assertJsonMissing(['implemented' => false]);
    expect($response->json('high_confidence_deals'))->toBeGreaterThanOrEqual(1);
    expect($response->json('low_confidence_deals'))->toBeGreaterThanOrEqual(1);
});

test('metrics endpoint returns real computed figures, not a stub', function () {
    $user = actingAsUser('admin');

    Opportunity::factory()->create([
        'tenant_id' => $user->company_id ?? 0,
        'owner_id' => $user->id,
        'amount' => 100000,
        'status' => 'open',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/einstein-forecasting/metrics');

    $response->assertOk();
    $response->assertJsonStructure([
        'accuracy_rate',
        'win_probability',
        'average_deal_size',
        'sales_cycle_length',
        'pipeline_health_score',
    ]);
    $response->assertJsonMissing(['implemented' => false]);
});

test('by-representative and adjust are wired for real, not stubs', function () {
    $user = actingAsUser('admin');

    $byRep = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/einstein-forecasting/by-representative');
    $byRep->assertOk();
    // Real per-rep breakdown shape (no rep_id given), not the old stub shape.
    $byRep->assertJsonStructure(['representatives']);
    $byRep->assertJsonMissing(['implemented' => false]);

    $adjust = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/einstein-forecasting/adjust', [
            'adjustment_type' => 'forecast',
            'adjustment_factor' => 1.1,
        ]);
    $adjust->assertOk();
    $adjust->assertJsonStructure(['original_forecast', 'adjusted_forecast', 'adjustment_factor', 'confidence_impact']);
});

test('by-product remains an honest documented stub, not silently broken', function () {
    $user = actingAsUser('admin');

    $byProduct = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/einstein-forecasting/by-product');
    $byProduct->assertOk();
    expect($byProduct->json('implemented'))->toBeFalse();
});
