<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\AiContextualAssistantService;

uses(RefreshDatabase::class);

/**
 * Tests for Reporting AI Assist endpoint.
 *
 * @group reporting
 * @group ai-assist
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'manager']);
    $this->actingAs($this->user);

    $this->mock(AiContextualAssistantService::class, function ($mock) {
        $mock->shouldReceive('getGuidance')
            ->andReturn([
                'enabled'             => false,
                'what_to_do'          => 'Générer un rapport OHADA',
                'how_to_do'           => ['Sélectionner la période', 'Choisir le type de rapport', 'Exporter au format PDF'],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => ['Les rapports OHADA sont requis pour la conformité légale'],
            ]);
    });
});

test('reporting ai assist requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/reporting/ai/assist', ['action' => 'ohada_report'])
        ->assertStatus(401);
});

test('reporting ai assist returns guidance for ohada_report', function () {
    $response = $this->postJson('/api/v1/reporting/ai/assist', [
        'action'  => 'ohada_report',
        'context' => ['report_type' => 'balance_sheet', 'currency' => 'XOF'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['enabled', 'what_to_do', 'how_to_do', 'decision_indicators', 'warnings', 'next_actions', 'tips']);
});

test('reporting ai assist returns guidance for nl_query', function () {
    $response = $this->postJson('/api/v1/reporting/ai/assist', [
        'action'  => 'nl_query',
        'context' => ['example_query' => 'Ventes par région ce mois'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('reporting ai assist returns guidance for create_report', function () {
    $response = $this->postJson('/api/v1/reporting/ai/assist', [
        'action' => 'create_report',
        'locale' => 'fr',
    ]);

    $response->assertStatus(200);
});

test('reporting ai assist returns guidance for export_report', function () {
    $response = $this->postJson('/api/v1/reporting/ai/assist', [
        'action'  => 'export_report',
        'context' => ['format' => 'pdf', 'report_type' => 'income_statement'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('reporting ai assist returns guidance for schedule_report', function () {
    $response = $this->postJson('/api/v1/reporting/ai/assist', [
        'action'  => 'schedule_report',
        'context' => ['frequency' => 'monthly', 'recipients' => 2],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('reporting ai assist validates required action', function () {
    $this->postJson('/api/v1/reporting/ai/assist', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('reporting ai assist accepts XOF currency in context (UEMOA)', function () {
    $response = $this->postJson('/api/v1/reporting/ai/assist', [
        'action'  => 'ohada_report',
        'context' => ['currency' => 'XOF', 'country' => 'CI'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('reporting ai assist accepts XAF currency in context (CEMAC)', function () {
    $response = $this->postJson('/api/v1/reporting/ai/assist', [
        'action'  => 'ohada_report',
        'context' => ['currency' => 'XAF', 'country' => 'CM'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('reporting ai assist response has correct types', function () {
    $response = $this->postJson('/api/v1/reporting/ai/assist', [
        'action' => 'ohada_report',
    ]);

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['enabled'])->toBeBool()
        ->and($data['what_to_do'])->toBeString()
        ->and($data['how_to_do'])->toBeArray()
        ->and($data['warnings'])->toBeArray();
});
