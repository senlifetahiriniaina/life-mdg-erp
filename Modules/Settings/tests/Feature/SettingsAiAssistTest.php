<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\AiContextualAssistantService;

uses(RefreshDatabase::class);

/**
 * Tests for Settings AI Assist endpoint.
 *
 * @group settings
 * @group ai-assist
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user);

    $this->mock(AiContextualAssistantService::class, function ($mock) {
        $mock->shouldReceive('getGuidance')
            ->andReturn([
                'enabled'             => false,
                'what_to_do'          => 'Configurer les paramètres du module',
                'how_to_do'           => ['Sélectionner le module', 'Modifier les valeurs', 'Sauvegarder'],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => ['Les paramètres sont appliqués immédiatement'],
            ]);
    });
});

test('settings ai assist requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/settings/ai/assist', ['action' => 'configure_module'])
        ->assertStatus(401);
});

test('settings ai assist returns guidance for configure_module', function () {
    $response = $this->postJson('/api/v1/settings/ai/assist', [
        'action' => 'configure_module',
        'locale' => 'fr',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['enabled', 'what_to_do', 'how_to_do', 'decision_indicators', 'warnings', 'next_actions', 'tips']);
});

test('settings ai assist returns guidance for smart_defaults', function () {
    $response = $this->postJson('/api/v1/settings/ai/assist', [
        'action'  => 'smart_defaults',
        'context' => ['country' => 'SN', 'industry' => 'retail'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('settings ai assist returns guidance for tax_setup', function () {
    $response = $this->postJson('/api/v1/settings/ai/assist', [
        'action'  => 'tax_setup',
        'context' => ['country' => 'CI', 'tax_type' => 'TVA', 'rate' => 18],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('settings ai assist validates required action', function () {
    $this->postJson('/api/v1/settings/ai/assist', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('settings ai assist response structure is correct', function () {
    $response = $this->postJson('/api/v1/settings/ai/assist', [
        'action' => 'configure_module',
    ]);

    $response->assertStatus(200);
    expect($response->json('enabled'))->toBeBool()
        ->and($response->json('how_to_do'))->toBeArray();
});
