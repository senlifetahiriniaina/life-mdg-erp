<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('metrics endpoint returns prometheus text format', function () {
    $response = $this->get('/api/metrics', ['Accept' => 'text/plain']);

    // Accept 200 (ok) or 401 if METRICS_TOKEN is set in test env
    expect($response->status())->toBeIn([200, 401]);

    if ($response->status() === 200) {
        expect($response->headers->get('Content-Type'))->toContain('text/plain');
        expect($response->content())->toContain('widehalo_up 1');
        expect($response->content())->toContain('widehalo_db_up');
        expect($response->content())->toContain('widehalo_module_enabled');
    }
});

test('metrics endpoint requires token when METRICS_TOKEN is configured', function () {
    config(['app.metrics_token' => 'secret-prometheus-token']);

    $this->get('/api/metrics')->assertUnauthorized();

    $this->withToken('secret-prometheus-token')
        ->get('/api/metrics')
        ->assertOk();
});
