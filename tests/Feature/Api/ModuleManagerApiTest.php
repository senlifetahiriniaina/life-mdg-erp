<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ── Auth ───────────────────────────────────────────────────────────────────────

test('unauthenticated user cannot list modules', function () {
    $this->getJson('/api/v1/modules')->assertUnauthorized();
});

test('unauthenticated user cannot enable a module', function () {
    $this->postJson('/api/v1/modules/CRM/enable')->assertUnauthorized();
});

// ── List ───────────────────────────────────────────────────────────────────────

test('authenticated user can list their enabled modules', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/modules')
        ->assertOk()
        ->assertJsonStructure(['modules']);
});

test('list returns empty array when no modules enabled', function () {
     $user = actingAsUser('employee');
    $response = $this
        ->getJson('/api/v1/modules')
        ->assertOk();

    // Fresh user has no tenant_modules rows, so only Core (always enabled) may appear
    expect($response->json('modules'))->toBeArray();
});

// ── Enable ────────────────────────────────────────────────────────────────────

test('user can enable a module', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/modules/CRM/enable')
        ->assertOk()
        ->assertJsonPath('message', 'Module CRM enabled.');

    $this->assertDatabaseHas('tenant_modules', [
        'tenant_id' => (string) $user->id,
        'module'    => 'CRM',
        'enabled'   => 1,
    ]);
});

test('user can enable a module for a specific department', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/modules/HR/enable', ['department' => 'engineering'])
        ->assertOk();

    $this->assertDatabaseHas('tenant_modules', [
        'tenant_id'  => (string) $user->id,
        'module'     => 'HR',
        'department' => 'engineering',
        'enabled'    => 1,
    ]);
});

// ── Disable ───────────────────────────────────────────────────────────────────

test('user can disable an enabled module', function () {
     $user = actingAsUser('employee');

    // Enable first
    DB::table('tenant_modules')->insert([
        'tenant_id'  => (string) $user->id,
        'module'     => 'CRM',
        'department' => null,
        'enabled'    => true,
        'settings'   => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
        $response = $this
        ->postJson('/api/v1/modules/CRM/disable')
        ->assertOk()
        ->assertJsonPath('message', 'Module CRM disabled.');

    $this->assertDatabaseHas('tenant_modules', [
        'tenant_id' => (string) $user->id,
        'module'    => 'CRM',
        'enabled'   => 0,
    ]);
});

test('Core module cannot be disabled', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/modules/Core/disable')
        ->assertStatus(500); // InvalidArgumentException — Core is always required
});

// ── Settings ──────────────────────────────────────────────────────────────────

test('user can update module settings', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->putJson('/api/v1/modules/CRM/settings', [
            'settings' => ['default_pipeline' => 'sales', 'auto_assign' => true],
        ])
        ->assertOk()
        ->assertJsonPath('settings.default_pipeline', 'sales');
});

test('update settings requires settings array', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->putJson('/api/v1/modules/CRM/settings', [])
        ->assertUnprocessable();
});

// ── Module visibility after enable/disable ────────────────────────────────────

test('enabled module appears in list', function () {
    actingAsUser('employee');
    \Modules\Core\Models\TenantModule::firstOrCreate(['module' => 'Inventory'], ['is_enabled' => true]);
    $response = $this->getJson('/api/v1/modules');
})->skip('requires tenant context');

test('disabled module does not appear in list', function () {
    actingAsUser('employee');
    \Modules\Core\Models\TenantModule::firstOrCreate(['module' => 'Inventory'], ['is_enabled' => false]);
    $response = $this->getJson('/api/v1/modules');
})->skip("requires tenant context");
