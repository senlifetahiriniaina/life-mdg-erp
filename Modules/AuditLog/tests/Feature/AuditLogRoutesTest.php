<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\AiContextualAssistantService;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Tests for AuditLog API routes.
 *
 * @group auditlog
 * @group api
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    Permission::firstOrCreate(['name' => 'auditlog.logs.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('auditlog.logs.view');
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

test('ai assist returns guidance for view_audit_log action', function () {
    $response = $this->postJson('/api/v1/audit-logs/ai/assist', [
        'action' => 'view_audit_log',
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

test('ai assist returns guidance for filter_events action', function () {
    $response = $this->postJson('/api/v1/audit-logs/ai/assist', [
        'action' => 'filter_events',
        'locale' => 'fr',
    ]);

    $response->assertStatus(200);
});

test('ai assist validates required action field', function () {
    $this->postJson('/api/v1/audit-logs/ai/assist', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('ai assist response has correct structure', function () {
    $response = $this->postJson('/api/v1/audit-logs/ai/assist', [
        'action' => 'view_audit_log',
    ]);

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKeys(['enabled', 'what_to_do', 'how_to_do', 'decision_indicators', 'warnings', 'next_actions', 'tips'])
        ->and($data['enabled'])->toBeBool()
        ->and($data['how_to_do'])->toBeArray();
});

// Chantier 32.4: the previous versions of the 3 tests above used fictional
// action keys (view_logs, anomaly_detection, compliance_report) — none of
// which are registered in AiContextualAssistantService::supportedModules()
// (the real, registered AuditLog actions are view_audit_log, export_audit,
// filter_events) — corrected to the real ones. Since every test in this
// file runs behind the beforeEach mock above, real (unmocked) fallback-text
// coverage for these 3 actions lives in Chantier32AuditLogDeepAuditTest.php
// instead, where it can actually exercise the live service.
