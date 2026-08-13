<?php

declare(strict_types=1);
use Modules\Helpdesk\Services\AI\HelpdeskAIService;


test('can predict ticket escalation', function () {
    actingAsUser('employee');

    $mock = Mockery::mock(HelpdeskAIService::class);
    $mock->shouldReceive('predictEscalation')
        ->once()
        ->andReturn(['prediction' => '{"escalation_risk":"high","risk_score":75}', 'ticket_id' => 1]);
    app()->instance(HelpdeskAIService::class, $mock);

    $this->postJson('/api/v1/helpdesk/ai/predict-escalation', [
        'ticket_id' => 1,
        'ticket_data' => ['subject' => 'URGENT: System down', 'wait_hours' => 48, 'replies' => 5],
    ])->assertOk()->assertJsonStructure(['prediction', 'ticket_id']);
});

test('can query kb chatbot', function () {
    actingAsUser('employee');

    $mock = Mockery::mock(HelpdeskAIService::class);
    $mock->shouldReceive('kbChatbotResponse')
        ->once()
        ->andReturn(['response' => '{"answer":"To reset your password..."}', 'query' => 'How do I reset my password?']);
    app()->instance(HelpdeskAIService::class, $mock);

    $this->postJson('/api/v1/helpdesk/ai/kb-chatbot', [
        'query' => 'How do I reset my password?',
    ])->assertOk()->assertJsonStructure(['response', 'query']);
});
