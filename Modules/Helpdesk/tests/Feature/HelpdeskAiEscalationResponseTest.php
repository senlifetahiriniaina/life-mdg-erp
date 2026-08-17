<?php

declare(strict_types=1);

// Lean coverage for the two REAL, routed AI endpoints that
// `Modules/Helpdesk/tests/Feature/PredictiveEscalationTest.php` (38 tests, phantom
// `POST /api/v1/helpdesk/escalation/predict` + `/escalation/batch-predict` +
// `/escalation/accuracy-metrics` contract — no route, no migration, ever backed it)
// and `Modules/Helpdesk/tests/Feature/AiResponseTest.php` (37 tests, phantom
// `POST /api/v1/helpdesk/ai-response/suggest` + `/ai-response/variants` contract — same
// story) were actually trying to reach. Both phantom files were deleted.
//
// The real, live surface — per `HelpdeskAIController` + `HelpdeskAIService`, both routed
// under `throttle:ai`/`prefix('helpdesk/ai')` in `Modules/Helpdesk/routes/api.php` — is:
//   POST /api/v1/helpdesk/ai/predict-escalation → HelpdeskAIService::predictEscalation()
//   POST /api/v1/helpdesk/ai/suggest-response   → HelpdeskAIService::suggestResponse()
// Both delegate to the Core `AIService` (Anthropic/OpenAI/DeepSeek, provider-agnostic) via
// `$this->ai->ask(...)`, so — matching the existing sibling coverage in
// `HelpdeskAiExtendedTest.php` (`can predict ticket escalation`, `can query kb chatbot`) —
// these tests mock `HelpdeskAIService` rather than hitting a real LLM provider.

use Modules\Helpdesk\Services\AI\HelpdeskAIService;

test('predict-escalation requires authentication', function () {
    $this->postJson('/api/v1/helpdesk/ai/predict-escalation', [
        'ticket_id' => 1,
        'ticket_data' => ['subject' => 'Down'],
    ])->assertUnauthorized();
});

test('predict-escalation validates required fields', function () {
    actingAsUser('employee');

    $this->postJson('/api/v1/helpdesk/ai/predict-escalation', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ticket_id', 'ticket_data']);
});

test('predict-escalation returns the AI risk prediction for a ticket', function () {
    actingAsUser('employee');

    $mock = Mockery::mock(HelpdeskAIService::class);
    $mock->shouldReceive('predictEscalation')
        ->once()
        ->with(42, ['subject' => 'URGENT: System down', 'wait_hours' => 48])
        ->andReturn([
            'prediction' => '{"escalation_risk":"high","risk_score":75,"risk_factors":["long wait"],"recommended_actions":[{"action":"escalate","urgency":"high"}]}',
            'ticket_id' => 42,
        ]);
    app()->instance(HelpdeskAIService::class, $mock);

    $this->postJson('/api/v1/helpdesk/ai/predict-escalation', [
        'ticket_id' => 42,
        'ticket_data' => ['subject' => 'URGENT: System down', 'wait_hours' => 48],
    ])
        ->assertOk()
        ->assertJsonStructure(['prediction', 'ticket_id'])
        ->assertJson(['ticket_id' => 42]);
});

test('suggest-response requires authentication', function () {
    $this->postJson('/api/v1/helpdesk/ai/suggest-response', [
        'subject' => 'Cannot login',
        'description' => 'I cannot access my account',
    ])->assertUnauthorized();
});

test('suggest-response validates required fields', function () {
    actingAsUser('employee');

    $this->postJson('/api/v1/helpdesk/ai/suggest-response', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'description']);
});

test('suggest-response returns an AI-drafted reply for a ticket', function () {
    actingAsUser('employee');

    $mock = Mockery::mock(HelpdeskAIService::class);
    $mock->shouldReceive('suggestResponse')
        ->once()
        ->with('Cannot login', 'I cannot access my account', [])
        ->andReturn('Thank you for reaching out — please try resetting your password via the link below.');
    app()->instance(HelpdeskAIService::class, $mock);

    $response = $this->postJson('/api/v1/helpdesk/ai/suggest-response', [
        'subject' => 'Cannot login',
        'description' => 'I cannot access my account',
    ])
        ->assertOk()
        ->assertJsonStructure(['response']);

    expect($response->json('response'))->toBeString();
    expect(strlen($response->json('response')))->toBeGreaterThan(10);
});

test('suggest-response passes previous comments through as conversational context', function () {
    actingAsUser('employee');

    $mock = Mockery::mock(HelpdeskAIService::class);
    $mock->shouldReceive('suggestResponse')
        ->once()
        ->with('Billing issue', 'Why was I charged twice?', ['We are looking into it.'])
        ->andReturn('We have identified the duplicate charge and are processing a refund.');
    app()->instance(HelpdeskAIService::class, $mock);

    $this->postJson('/api/v1/helpdesk/ai/suggest-response', [
        'subject' => 'Billing issue',
        'description' => 'Why was I charged twice?',
        'previous_comments' => ['We are looking into it.'],
    ])
        ->assertOk()
        ->assertJson(['response' => 'We have identified the duplicate charge and are processing a refund.']);
});
