<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('health endpoint returns a structured response', function () {
    // In the test environment Redis is unavailable, so status may be 'degraded' (503).
    // We only assert the response shape — callers should key off the 'status' field.
    $response = $this->getJson('/api/health');

    $response->assertJsonStructure([
        'status',
        'version',
        'env',
        'timestamp',
        'checks' => [
            'database',
            'redis',
            'queue',
            'storage',
            'modules',
        ],
    ]);

    expect($response->json('status'))->toBeIn(['ok', 'degraded']);
    expect($response->json('checks.database.status'))->toBe('ok');
    expect($response->json('checks.storage.status'))->toBe('ok');
});

test('health endpoint is unauthenticated', function () {
    $code = $this->getJson('/api/health')->status();
    expect($code)->toBeIn([200, 503]); // 503 when degraded (e.g. no Redis in CI)
});
