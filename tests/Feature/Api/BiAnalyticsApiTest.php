<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can fetch analytics summary with default months', function () {
    actingAsUser('manager');

    $response = $this
        ->getJson('/api/v1/bi/analytics/summary');

    $response->assertOk()
        ->assertJsonStructure([
            'revenue'      => ['labels', 'data'],
            'tickets',
            'leads'        => ['labels', 'data'],
            'top_products',
            'kpi_snapshot',
        ]);
});

test('kpi_snapshot returns array of label/value/trend/change objects', function () {
    actingAsUser('manager');

    $response = $this
        ->getJson('/api/v1/bi/analytics/summary');

    $response->assertOk();

    $snapshot = $response->json('kpi_snapshot');
    expect($snapshot)->toBeArray()->not->toBeEmpty();

    foreach ($snapshot as $kpi) {
        expect($kpi)->toHaveKeys(['label', 'value', 'trend', 'change']);
        expect($kpi['trend'])->toBeIn(['up', 'down', 'flat']);
    }
});

test('revenue labels match requested month count', function () {
    actingAsUser('manager');

    foreach ([3, 6, 12] as $months) {
        $response = $this
            ->getJson("/api/v1/bi/analytics/summary?months={$months}");

        $response->assertOk();
        expect($response->json('revenue.labels'))->toHaveCount($months);
        expect($response->json('revenue.data'))->toHaveCount($months);
    }
});

test('months parameter is clamped between 1 and 24', function () {
    actingAsUser('manager');

    $response = $this
        ->getJson('/api/v1/bi/analytics/summary?months=99');
    $response->assertOk();
    expect($response->json('revenue.labels'))->toHaveCount(24);

    $response = $this
        ->getJson('/api/v1/bi/analytics/summary?months=0');
    $response->assertOk();
    expect($response->json('revenue.labels'))->toHaveCount(1);
});

test('unauthenticated user is rejected', function () {
    $this->getJson('/api/v1/bi/analytics/summary')->assertUnauthorized();
});
