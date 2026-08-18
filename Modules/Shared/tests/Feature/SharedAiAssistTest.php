<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\AiContextualAssistantService;

uses(RefreshDatabase::class);

/**
 * Tests for Shared AI Assist endpoint.
 *
 * @group shared
 * @group ai-assist
 */

beforeEach(function () {
    // Chantier 10: 'role' is the phantom column (never read by real RBAC —
    // Spatie roles are). Modules/Shared/routes/api.php gained a real
    // module:/role: gate (previously had NO middleware at all on this
    // group, not even auth:sanctum — see CLAUDE.md's Settings/Shared note),
    // so a user with no real assigned role now 403s before reaching the
    // controller at all.
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->assignRole('employee');
    $this->actingAs($this->user);

    $this->mock(AiContextualAssistantService::class, function ($mock) {
        $mock->shouldReceive('getGuidance')
            ->andReturn([
                'enabled'             => false,
                'what_to_do'          => 'Configurer la devise et la localisation',
                'how_to_do'           => ['Sélectionner le pays', 'Choisir la devise', 'Configurer la TVA'],
                'decision_indicators' => [
                    ['label' => 'Devise recommandée', 'value' => 'XOF', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => ['XOF est la devise officielle des pays UEMOA'],
            ]);
    });
});

test('shared ai assist requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/shared/ai/assist', ['action' => 'select_currency'])
        ->assertStatus(401);
});

test('shared ai assist returns guidance for select_currency', function () {
    $response = $this->postJson('/api/v1/shared/ai/assist', [
        'action'  => 'select_currency',
        'context' => ['country' => 'SN'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['enabled', 'what_to_do', 'how_to_do', 'decision_indicators', 'warnings', 'next_actions', 'tips']);
});

test('shared ai assist returns guidance for configure_locale', function () {
    $response = $this->postJson('/api/v1/shared/ai/assist', [
        'action'  => 'configure_locale',
        'context' => ['country' => 'MA', 'language' => 'ar'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('shared ai assist returns guidance for tax_lookup', function () {
    $response = $this->postJson('/api/v1/shared/ai/assist', [
        'action'  => 'tax_lookup',
        'context' => ['country' => 'CI', 'tax_type' => 'TVA'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('shared ai assist validates required action', function () {
    $this->postJson('/api/v1/shared/ai/assist', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('shared ai assist works with CEMAC countries', function () {
    $response = $this->postJson('/api/v1/shared/ai/assist', [
        'action'  => 'select_currency',
        'context' => ['country' => 'CM', 'currency' => 'XAF'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('shared ai assist response includes decision indicators for currency', function () {
    $response = $this->postJson('/api/v1/shared/ai/assist', [
        'action'  => 'select_currency',
        'context' => ['country' => 'SN'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
    expect($response->json('decision_indicators'))->toBeArray();
});
