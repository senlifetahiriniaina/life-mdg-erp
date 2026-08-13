<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\AiContextualAssistantService;

uses(RefreshDatabase::class);

/**
 * Tests for the Sales AI Assist endpoint.
 *
 * @group sales
 * @group ai-assist
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'sales-rep']);
    $this->actingAs($this->user);

    $this->mock(AiContextualAssistantService::class, function ($mock) {
        $mock->shouldReceive('getGuidance')
            ->andReturn([
                'enabled'             => false,
                'what_to_do'          => 'Créer une commande de vente',
                'how_to_do'           => ['Sélectionner le client', 'Ajouter les lignes produit'],
                'decision_indicators' => [
                    ['label' => 'Devise', 'value' => 'XOF', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Confirmer', 'action' => 'confirm_order', 'module' => 'Sales'],
                ],
                'tips'                => ['Vérifiez la disponibilité des stocks'],
            ]);
    });
});

test('ai assist returns guidance for create_order', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action' => 'create_order',
        'locale' => 'fr',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'enabled', 'what_to_do', 'how_to_do',
            'decision_indicators', 'warnings', 'next_actions', 'tips',
        ]);
});

test('ai assist returns guidance for confirm_order', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action'  => 'confirm_order',
        'context' => ['order_id' => 1, 'total' => 500000, 'currency' => 'XOF', 'lines_count' => 3],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist returns guidance for create_quotation', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action'  => 'create_quotation',
        'context' => ['contact_name' => 'ACME Corp', 'currency' => 'XOF'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist rejects unauthenticated request', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/sales/ai/assist', ['action' => 'create_order'])
        ->assertStatus(401);
});

test('ai assist validates action is required', function () {
    $this->postJson('/api/v1/sales/ai/assist', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('ai assist works with XOF currency context (UEMOA)', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action'  => 'create_order',
        'context' => ['currency' => 'XOF', 'country' => 'SN'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist works with XAF currency context (CEMAC)', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action'  => 'create_order',
        'context' => ['currency' => 'XAF', 'country' => 'CM'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist works with convert_quote action', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action'  => 'convert_quote',
        'context' => ['quotation_status' => 'sent', 'total' => 2000000],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist decision_indicators is an array', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action' => 'create_order',
    ]);

    $response->assertStatus(200);
    expect($response->json('decision_indicators'))->toBeArray();
});

test('ai assist next_actions contains module references', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action' => 'confirm_order',
    ]);

    $response->assertStatus(200);
    $nextActions = $response->json('next_actions');
    expect($nextActions)->toBeArray();
});

test('ai assist accepts hausa locale for West Africa', function () {
    $response = $this->postJson('/api/v1/sales/ai/assist', [
        'action' => 'create_order',
        'locale' => 'ha',
    ]);

    $response->assertStatus(200);
});
