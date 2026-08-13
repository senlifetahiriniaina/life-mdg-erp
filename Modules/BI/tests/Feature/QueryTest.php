<?php

declare(strict_types=1);

use Modules\BI\Models\BiQuery;


test('can run a SELECT query', function () {
    $user = actingAsUser('manager');

    // Create a simple query
    $query = BiQuery::create([
        'name' => 'Test Query',
        'sql_query' => 'SELECT 1 as value',
        'datasource' => 'default',
        'result_cache_ttl' => 0,
        'is_public' => false,
        'created_by' => $user->id,
    ]);

    $response = $this
        ->postJson("/api/v1/bi/queries/{$query->id}/run");

    $response->assertOk()
        ->assertJsonStructure(['columns', 'rows', 'duration_ms']);
});

test('SELECT query blocked if contains DELETE keyword', function () {
    actingAsUser('admin');

    $response = $this
        ->postJson('/api/v1/bi/queries/run-raw', [
            'sql' => 'DELETE FROM users',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'DELETE') || str_contains($msg, 'forbidden') || str_contains($msg, 'SELECT'));
});

test('SELECT query blocked if contains DROP keyword', function () {
    actingAsUser('admin');

    $response = $this
        ->postJson('/api/v1/bi/queries/run-raw', [
            'sql' => 'DROP TABLE users',
        ]);

    $response->assertStatus(422);
});

test('can export a saved query as CSV', function () {
    $user = actingAsUser('manager');

    $query = BiQuery::create([
        'name' => 'CSV Export Query',
        'sql_query' => 'SELECT 1 as id, \'test\' as name',
        'datasource' => 'default',
        'result_cache_ttl' => 0,
        'is_public' => false,
        'created_by' => $user->id,
    ]);

    $response = $this
        ->get("/api/v1/bi/queries/{$query->id}/export?format=csv");

    $response->assertOk();
    $contentType = $response->headers->get('Content-Type');
    expect($contentType)->toContain('text/csv');
});

test('unauthenticated user cannot access BI queries', function () {
    $this->getJson('/api/v1/bi/queries')
        ->assertUnauthorized();
});
