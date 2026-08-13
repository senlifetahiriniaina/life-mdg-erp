<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Services\AI\AIService;

uses(RefreshDatabase::class);

// ── Auth ───────────────────────────────────────────────────────────────────────

test('unauthenticated user cannot ask the AI assistant', function () {
    $this->postJson('/api/v1/ai/ask', ['question' => 'Hello?'])->assertUnauthorized();
});

test('unauthenticated user cannot request data analysis', function () {
    $this->postJson('/api/v1/ai/analyze', ['data' => []])->assertUnauthorized();
});

// ── /ai/ask ───────────────────────────────────────────────────────────────────

test('ask returns an answer from the AI service', function () {
     $user = actingAsUser('employee');

    $mock = Mockery::mock(AIService::class);
    $mock->shouldReceive('ask')
        ->once()
        ->with('What is the revenue trend?', [], null, \Mockery::any())
        ->andReturn('Revenue has grown 12% quarter-over-quarter.');

    app()->instance(AIService::class, $mock);
        $response = $this
        ->postJson('/api/v1/ai/ask', ['question' => 'What is the revenue trend?'])
        ->assertOk()
        ->assertJsonPath('answer', 'Revenue has grown 12% quarter-over-quarter.');
});

test('ask passes module context to AI service when provided', function () {
     $user = actingAsUser('employee');

    $mock = Mockery::mock(AIService::class);
    $mock->shouldReceive('ask')
        ->once()
        ->with('Summarise top leads', [], 'CRM', \Mockery::any())
        ->andReturn('You have 5 hot leads.');

    app()->instance(AIService::class, $mock);
        $response = $this
        ->postJson('/api/v1/ai/ask', ['question' => 'Summarise top leads', 'module' => 'CRM'])
        ->assertOk()
        ->assertJsonPath('answer', 'You have 5 hot leads.');
});

test('ask validates question is required', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/ai/ask', [])
        ->assertUnprocessable();
});

test('ask validates question max length', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/ai/ask', ['question' => str_repeat('x', 2001)])
        ->assertUnprocessable();
});

// ── /ai/analyze ───────────────────────────────────────────────────────────────

test('analyze returns insight from AI service', function () {
     $user = actingAsUser('employee');

    $mock = Mockery::mock(AIService::class);
    $mock->shouldReceive('analyzeData')
        ->once()
        ->with(['revenue' => 10000, 'costs' => 7000], 'analyst', \Mockery::any())
        ->andReturn('Margin is 30%. Healthy growth.');

    app()->instance(AIService::class, $mock);
        $response = $this
        ->postJson('/api/v1/ai/analyze', ['data' => ['revenue' => 10000, 'costs' => 7000]])
        ->assertOk()
        ->assertJsonPath('insight', 'Margin is 30%. Healthy growth.');
});

test('analyze accepts valid type values', function () {
     $user = actingAsUser('employee');

    $mock = Mockery::mock(AIService::class);
    $mock->shouldReceive('analyzeData')
        ->once()
        ->with(\Mockery::any(), 'accountant', \Mockery::any())
        ->andReturn('Accounting analysis done.');

    app()->instance(AIService::class, $mock);
        $response = $this
        ->postJson('/api/v1/ai/analyze', ['data' => ['items' => []], 'type' => 'accountant'])
        ->assertOk();
});

test('analyze rejects invalid type', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/ai/analyze', ['data' => [], 'type' => 'hacker'])
        ->assertUnprocessable();
});

test('analyze requires data field', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/ai/analyze', [])
        ->assertUnprocessable();
});
