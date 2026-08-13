<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Helpdesk\Models\ChatSession;


test('visitor can start chat session without auth', function () {
    $this->postJson('/api/v1/helpdesk/chat/sessions', [
        'visitor_id' => 'test-visitor-uuid-001',
        'visitor_name' => 'John Doe',
    ])
        ->assertStatus(201)
        ->assertJsonPath('session.status', 'waiting')
        ->assertJsonPath('session.visitor_id', 'test-visitor-uuid-001');
});

test('visitor can send message to a session', function () {
    $session = ChatSession::create([
        'visitor_id' => 'visitor-abc',
        'status' => 'waiting',
        'started_at' => now(),
        'channel' => 'web',
    ]);

    $this->postJson("/api/v1/helpdesk/chat/sessions/{$session->id}/messages", [
        'sender_type' => 'visitor',
        'message' => 'Hello, I need help!',
    ])
        ->assertStatus(201)
        ->assertJsonPath('message', 'Hello, I need help!');
});

test('authenticated agent can claim a waiting session', function () {
    $agent = User::factory()->create();
    $session = ChatSession::create([
        'visitor_id' => 'visitor-xyz',
        'status' => 'waiting',
        'started_at' => now(),
        'channel' => 'web',
    ]);

    $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/helpdesk/chat/sessions/{$session->id}/assign")
        ->assertOk()
        ->assertJsonPath('status', 'active');
});

test('authenticated agent can send message as agent', function () {
    $agent = User::factory()->create();
    $session = ChatSession::create([
        'visitor_id' => 'visitor-def',
        'status' => 'active',
        'started_at' => now(),
        'assigned_agent_id' => $agent->id,
        'channel' => 'web',
    ]);

    $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/helpdesk/chat/sessions/{$session->id}/messages", [
            'sender_type' => 'agent',
            'message' => 'How can I assist you?',
        ])
        ->assertStatus(201)
        ->assertJsonPath('sender_type', 'agent');
});

test('agent can convert chat session to ticket', function () {
    $agent = User::factory()->create();
    $session = ChatSession::create([
        'visitor_id' => 'visitor-ghi',
        'visitor_name' => 'Jane Visitor',
        'status' => 'active',
        'started_at' => now(),
        'assigned_agent_id' => $agent->id,
        'channel' => 'web',
    ]);

    $session->messages()->create([
        'sender_type' => 'visitor',
        'message' => 'I have a billing issue',
        'type' => 'text',
    ]);

    $this->actingAs($agent, 'sanctum')
        ->postJson("/api/v1/helpdesk/chat/sessions/{$session->id}/convert-to-ticket")
        ->assertStatus(201)
        ->assertJsonStructure(['ticket', 'session']);

    expect(ChatSession::find($session->id)->status)->toBe('closed');
});
