<?php

declare(strict_types=1);

use Modules\CRM\Models\Forecast;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Services\ForecastService;


it('can generate a forecast for a period', function () {
    $user = actingAsUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-Q2'])
        ->assertStatus(201);

    expect($response->json('period'))->toBe('2026-Q2');
    expect($response->json())->toHaveKey('pipeline_total');
    expect($response->json())->toHaveKey('ai_prediction');
    expect($response->json())->toHaveKey('confidence_pct');

    $this->assertDatabaseHas('crm_forecasts', ['period' => '2026-Q2']);
});

it('can list all forecasts', function () {
    $user = actingAsUser('admin');
    Forecast::factory()->count(3)->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/crm/forecasts')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

it('forecast pipeline uses opportunities weighted by probability', function () {
    actingAsUser('admin');

    Opportunity::factory()->create([
        'stage' => 'proposal',
        'amount' => 100000,
        'probability' => 50,
    ]);

    Opportunity::factory()->create([
        'stage' => 'negotiation',
        'amount' => 200000,
        'probability' => 80,
    ]);

    $service = app(ForecastService::class);
    $forecast = $service->generateForecast('2026-Q3');

    // pipeline = 100000 * 50/100 + 200000 * 80/100 = 50000 + 160000 = 210000
    expect((float) $forecast->pipeline_total)->toBe(210000.0);
    expect((float) $forecast->forecast_amount)->toBe(round(210000 * 0.8, 2));
    expect((float) $forecast->ai_prediction)->toBe(round(210000 * 0.85, 2));
});

it('regenerating a forecast for same period updates existing record', function () {
    $user = actingAsUser('admin');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-Q4'])
        ->assertStatus(201);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/crm/forecasts/generate', ['period' => '2026-Q4'])
        ->assertStatus(201);

    $this->assertDatabaseCount('crm_forecasts', 1);
});
