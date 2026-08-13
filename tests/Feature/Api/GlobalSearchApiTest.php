<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ── Auth ──────────────────────────────────────────────────────────────────────

test('global search requires authentication', function () {
    $this->getJson('/api/v1/search?q=test')->assertUnauthorized();
});

// ── Validation ────────────────────────────────────────────────────────────────

test('global search requires q parameter', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/search')
        ->assertUnprocessable();
});

test('global search requires at least 2 characters', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/search?q=a')
        ->assertUnprocessable();
});

test('global search rejects query longer than 200 characters', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/search?q=' . str_repeat('a', 201))
        ->assertUnprocessable();
});

// ── Response structure ────────────────────────────────────────────────────────

test('global search returns expected structure', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/search?q=test')
        ->assertOk()
        ->assertJsonStructure(['query', 'total', 'results']);
});

test('global search echoes back the query', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/search?q=Dupont')
        ->assertOk()
        ->assertJsonPath('query', 'Dupont');
});

test('global search total matches sum of result counts', function () {
     $user = actingAsUser('employee');
    $response = $this
        ->getJson('/api/v1/search?q=test')
        ->assertOk()
        ->json();

    $counted = collect($response['results'])->sum(fn($r) => count($r));
    expect($response['total'])->toBe($counted);
});

// ── Module filter ─────────────────────────────────────────────────────────────

test('modules filter restricts result keys to CRM', function () {
     $user = actingAsUser('employee');
    $response = $this
        ->getJson('/api/v1/search?q=test&modules=CRM')
        ->assertOk()
        ->json();

    expect(array_keys($response['results']))->each->toBeIn(['contacts', 'accounts']);
});

test('modules filter restricts result keys to HR', function () {
     $user = actingAsUser('employee');
    $response = $this
        ->getJson('/api/v1/search?q=test&modules=HR')
        ->assertOk()
        ->json();

    expect(array_keys($response['results']))->each->toBeIn(['employees']);
});

test('modules filter restricts result keys to Projects', function () {
     $user = actingAsUser('employee');
    $response = $this
        ->getJson('/api/v1/search?q=test&modules=Projects')
        ->assertOk()
        ->json();

    expect(array_keys($response['results']))->each->toBeIn(['projects', 'tasks']);
});

test('modules filter with multiple values works', function () {
     $user = actingAsUser('employee');
    $response = $this
        ->getJson('/api/v1/search?q=test&modules=CRM,HR')
        ->assertOk()
        ->json();

    $keys = array_keys($response['results']);
    foreach ($keys as $key) {
        expect($key)->toBeIn(['contacts', 'accounts', 'employees']);
    }
});

// ── Safe on missing tables ────────────────────────────────────────────────────

test('global search does not crash when module tables are absent', function () {
     $user = actingAsUser('employee');

    // Drop a module table to simulate a disabled module
    if (DB::getSchemaBuilder()->hasTable('crm_contacts')) {
        DB::statement('DROP TABLE crm_contacts');
    }

        $response = $this
        ->getJson('/api/v1/search?q=test')
        ->assertOk();
});

// ── Data match ────────────────────────────────────────────────────────────────

test('global search finds matching contact by email', function () {
     $user = actingAsUser('employee');

    if (!DB::getSchemaBuilder()->hasTable('crm_contacts')) {
        $this->markTestSkipped('crm_contacts table not present');
    }

    DB::table('crm_contacts')->insert([
        'first_name' => 'Jean',
        'last_name'  => 'Dupont',
        'email'      => 'jean.dupont@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $response = $this
        ->getJson('/api/v1/search?q=dupont&modules=CRM')
        ->assertOk()
        ->json();

    expect($response['total'])->toBeGreaterThan(0);
});
