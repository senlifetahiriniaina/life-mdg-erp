<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\BI\Models\BiDataSource;


test('can create a mysql data source', function () {
    $user = actingAsUser('manager');

    $response = $this
        ->postJson('/api/v1/bi/data-sources', [
            'name' => 'Production MySQL',
            'type' => 'mysql',
            'connection_config' => [
                'host' => 'db.example.com',
                'port' => 3306,
                'database' => 'erp',
                'username' => 'readonly',
                'password' => 'secret',
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'Production MySQL')
        ->assertJsonPath('type', 'mysql');
});

test('connection_config is encrypted in DB', function () {
    $user = User::factory()->create();

    $source = BiDataSource::create([
        'name' => 'Encrypted Source',
        'type' => 'postgresql',
        'connection_config' => [
            'host' => 'secure-db.example.com',
            'password' => 'super-secret',
        ],
        'created_by' => $user->id,
        'status' => 'inactive',
    ]);

    // Raw DB value should not contain plaintext password
    $raw = DB::table('bi_data_sources')
        ->where('id', $source->id)
        ->value('connection_config');

    expect($raw)->not->toContain('super-secret');
    expect($raw)->not->toContain('"password"');
});

test('can test connection and get result', function () {
    $user = actingAsUser('manager');

    $source = BiDataSource::create([
        'name' => 'Test Source',
        'type' => 'rest_api',
        'connection_config' => ['base_url' => 'http://invalid-host-that-does-not-exist.local'],
        'created_by' => $user->id,
        'status' => 'inactive',
    ]);

    $response = $this
        ->postJson("/api/v1/bi/data-sources/{$source->id}/test");

    $response->assertOk()
        ->assertJsonStructure(['success', 'latency_ms', 'status']);
});

test('unauthenticated user cannot access data sources', function () {
    $this->getJson('/api/v1/bi/data-sources')
        ->assertUnauthorized();
});
