<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AI\Services\AiContextualAssistantService;

uses(RefreshDatabase::class);

/**
 * Tests for Payroll AI Assist endpoint.
 *
 * @group payroll
 * @group ai-assist
 */

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'hr-manager']);
    $this->actingAs($this->user);

    $this->mock(AiContextualAssistantService::class, function ($mock) {
        $mock->shouldReceive('getGuidance')
            ->andReturn([
                'enabled'             => false,
                'what_to_do'          => 'Exécuter la paie mensuelle',
                'how_to_do'           => ['Vérifier les données employés', 'Générer les bulletins', 'Approuver et payer'],
                'decision_indicators' => [
                    ['label' => 'Employés actifs', 'value' => '45', 'status' => 'ok'],
                    ['label' => 'Devise', 'value' => 'XOF', 'status' => 'ok'],
                ],
                'warnings'            => [],
                'next_actions'        => [
                    ['label' => 'Approuver les bulletins', 'action' => 'approve_payroll', 'module' => 'Payroll'],
                ],
                'tips'                => ['Vérifiez les anomalies avant d\'approuver'],
            ]);
    });
});

test('payroll ai assist requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/payroll/ai/assist', ['action' => 'run_payroll'])
        ->assertStatus(401);
});

test('payroll ai assist returns guidance for run_payroll', function () {
    $response = $this->postJson('/api/v1/payroll/ai/assist', [
        'action' => 'run_payroll',
        'locale' => 'fr',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['enabled', 'what_to_do', 'how_to_do', 'decision_indicators', 'warnings', 'next_actions', 'tips']);
});

test('payroll ai assist returns guidance for generate_payslips', function () {
    $response = $this->postJson('/api/v1/payroll/ai/assist', [
        'action'  => 'generate_payslips',
        'context' => ['period' => '2026-05', 'employee_count' => 45, 'currency' => 'XOF'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('payroll ai assist returns guidance for approve_payroll', function () {
    $response = $this->postJson('/api/v1/payroll/ai/assist', [
        'action'  => 'approve_payroll',
        'context' => ['total_gross' => 135000000, 'currency' => 'XOF'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('payroll ai assist returns guidance for post_to_accounting', function () {
    $response = $this->postJson('/api/v1/payroll/ai/assist', [
        'action'  => 'post_to_accounting',
        'context' => ['period' => '2026-05', 'ohada_account' => '661'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('payroll ai assist validates required action', function () {
    $this->postJson('/api/v1/payroll/ai/assist', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('payroll ai assist accepts multiple African locales', function () {
    foreach (['fr', 'en', 'sw', 'ha'] as $locale) {
        $response = $this->postJson('/api/v1/payroll/ai/assist', [
            'action' => 'run_payroll',
            'locale' => $locale,
        ]);
        $response->assertStatus(200);
    }
});

test('payroll ai assist response includes decision indicators', function () {
    $response = $this->postJson('/api/v1/payroll/ai/assist', [
        'action' => 'run_payroll',
    ]);

    $response->assertStatus(200);
    expect($response->json('decision_indicators'))->toBeArray();
});

test('payroll ai assist response includes next_actions', function () {
    $response = $this->postJson('/api/v1/payroll/ai/assist', [
        'action' => 'generate_payslips',
    ]);

    $response->assertStatus(200);
    expect($response->json('next_actions'))->toBeArray();
});

test('payroll ai assist accepts XAF context for Cameroon', function () {
    $response = $this->postJson('/api/v1/payroll/ai/assist', [
        'action'  => 'run_payroll',
        'context' => ['currency' => 'XAF', 'country' => 'CM', 'period' => '2026-05'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});

test('payroll ai assist accepts XOF context for Senegal', function () {
    $response = $this->postJson('/api/v1/payroll/ai/assist', [
        'action'  => 'run_payroll',
        'context' => ['currency' => 'XOF', 'country' => 'SN', 'period' => '2026-05'],
        'locale'  => 'fr',
    ]);

    $response->assertStatus(200);
});
