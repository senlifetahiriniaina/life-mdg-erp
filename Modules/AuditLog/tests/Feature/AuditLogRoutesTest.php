<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\AiContextualAssistantService;

uses(RefreshDatabase::class);

/**
 * Tests for AuditLog API routes.
 *
 * @group auditlog
 * @group api
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user);

    $this->mock(AiContextualAssistantService::class, function ($mock) {
        $mock->shouldReceive('getGuidance')
            ->andReturn([
                'enabled'             => false,
                'what_to_do'          => 'Consulter les journaux d\'audit',
                'how_to_do'           => ['Filtrez par module', 'Exportez si nécessaire'],
                'decision_indicators' => [],
                'warnings'            => [],
                'next_actions'        => [],
                'tips'                => [],
            ]);
    });
});

test('audit logs index requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/audit-logs')
        ->assertStatus(401);
});

test('audit logs stats requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/audit-logs/stats')
        ->assertStatus(401);
});

test('audit logs export requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/audit-logs/export')
        ->assertStatus(401);
});

test('ai assist endpoint requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/audit-logs/ai/assist', ['action' => 'view_logs'])
        ->assertStatus(401);
});

test('ai assist returns guidance for view_logs action', function () {
    $response = $this->postJson('/api/v1/audit-logs/ai/assist', [
        'action' => 'view_logs',
        'locale' => 'fr',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['enabled', 'what_to_do', 'how_to_do']);
});

test('ai assist returns guidance for export_audit action', function () {
    $response = $this->postJson('/api/v1/audit-logs/ai/assist', [
        'action'  => 'export_audit',
        'context' => ['module' => 'Accounting', 'date_from' => '2026-01-01'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist returns guidance for anomaly_detection action', function () {
    $response = $this->postJson('/api/v1/audit-logs/ai/assist', [
        'action' => 'anomaly_detection',
        'locale' => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist validates required action field', function () {
    $this->postJson('/api/v1/audit-logs/ai/assist', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('ai assist accepts compliance_report action', function () {
    $response = $this->postJson('/api/v1/audit-logs/ai/assist', [
        'action'  => 'compliance_report',
        'context' => ['standard' => 'GDPR', 'period' => '2026-Q1'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist response has correct structure', function () {
    $response = $this->postJson('/api/v1/audit-logs/ai/assist', [
        'action' => 'view_logs',
    ]);

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKeys(['enabled', 'what_to_do', 'how_to_do', 'decision_indicators', 'warnings', 'next_actions', 'tips'])
        ->and($data['enabled'])->toBeBool()
        ->and($data['how_to_do'])->toBeArray();
});
