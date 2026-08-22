<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Messaging\Events\MessageSent;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\ConversationParticipant;
use Modules\Messaging\Models\Message;

uses(RefreshDatabase::class);

// Chantier 20 — locks in the empirical 7-layer verification for the new
// Messaging module: route, controller, model/data-format, security, RBAC.
// (Vue-layer reachability is verified separately, not by Pest.)

test('a user can create a direct conversation and it is reused on a second attempt', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);

    $response = $this->postJson('/api/v1/messaging/conversations', [
        'user_ids' => [$other->id],
    ]);

    $response->assertCreated();
    $conversationId = $response->json('data.id');
    expect(Conversation::find($conversationId)->type)->toBe('direct');
    expect(ConversationParticipant::where('conversation_id', $conversationId)->count())->toBe(2);

    // Re-requesting a conversation with the same 2 participants reuses the thread.
    $again = $this->postJson('/api/v1/messaging/conversations', [
        'user_ids' => [$other->id],
    ]);
    $again->assertOk();
    expect($again->json('data.id'))->toBe($conversationId);
    expect(Conversation::count())->toBe(1);
});

test('a group conversation is created for 2+ recipients', function () {
    $me = actingAsUser('employee');
    $u2 = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $u3 = \App\Models\User::factory()->create(['company_id' => $me->company_id]);

    $response = $this->postJson('/api/v1/messaging/conversations', [
        'user_ids' => [$u2->id, $u3->id],
        'name' => 'Equipe Projet',
    ]);

    $response->assertCreated();
    expect(Conversation::find($response->json('data.id'))->type)->toBe('group');
    expect(ConversationParticipant::where('conversation_id', $response->json('data.id'))->count())->toBe(3);
});

test('a participant can post a message and it broadcasts MessageSent', function () {
    Event::fake([MessageSent::class]);

    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $me->id]);
    $conversation->participants()->create(['user_id' => $me->id]);
    $conversation->participants()->create(['user_id' => $other->id]);

    $response = $this->postJson("/api/v1/messaging/conversations/{$conversation->id}/messages", [
        'body' => 'Bonjour équipe, le rapport est prêt.',
    ]);

    $response->assertCreated();
    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(1);
    Event::assertDispatched(MessageSent::class);
});

test('unread_count reflects messages since the participant last read the conversation', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $other->id]);
    $conversation->participants()->create(['user_id' => $me->id]);
    $conversation->participants()->create(['user_id' => $other->id]);
    $conversation->messages()->create(['sender_id' => $other->id, 'body' => 'Salut']);

    $index = $this->getJson('/api/v1/messaging/conversations');
    $index->assertOk();
    $row = collect($index->json('data'))->firstWhere('id', $conversation->id);
    expect($row['unread_count'])->toBe(1);

    // Reading the thread marks it read — a fresh index call reports 0 unread.
    $this->getJson("/api/v1/messaging/conversations/{$conversation->id}/messages")->assertOk();
    $index2 = $this->getJson('/api/v1/messaging/conversations');
    $row2 = collect($index2->json('data'))->firstWhere('id', $conversation->id);
    expect($row2['unread_count'])->toBe(0);
});

test('a non-participant cannot view or post in a conversation (RBAC/security)', function () {
    $me = actingAsUser('employee');
    $owner = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $stranger = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $owner->id]);
    $conversation->participants()->create(['user_id' => $owner->id]);
    $conversation->participants()->create(['user_id' => $me->id]);

    // $me is not a participant of a *different* conversation between owner and stranger.
    $otherConversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $owner->id]);
    $otherConversation->participants()->create(['user_id' => $owner->id]);
    $otherConversation->participants()->create(['user_id' => $stranger->id]);

    $this->getJson("/api/v1/messaging/conversations/{$otherConversation->id}")->assertForbidden();
    $this->getJson("/api/v1/messaging/conversations/{$otherConversation->id}/messages")->assertForbidden();
    $this->postJson("/api/v1/messaging/conversations/{$otherConversation->id}/messages", ['body' => 'intrusion'])
        ->assertForbidden();

    // But the conversation $me genuinely belongs to is reachable.
    $this->getJson("/api/v1/messaging/conversations/{$conversation->id}")->assertOk();
});

test('an unauthenticated request is rejected', function () {
    $this->getJson('/api/v1/messaging/conversations')->assertUnauthorized();
});

test('the messaging web route is reachable for an authenticated user', function () {
    actingAsUser('employee');

    $response = $this->get('/messaging');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Messaging/Index'));
});

test('the AI assist endpoint returns real fallback guidance without a configured provider', function () {
    actingAsUser('employee');

    $response = $this->postJson('/api/v1/messaging/ai/assist', [
        'action' => 'start_conversation',
    ]);

    $response->assertOk();
    expect($response->json('enabled'))->toBeFalse();
    // Chantier 32.28: this assertion alone (enabled === false) previously
    // passed regardless of whether 'Messaging' was even registered in
    // AiContextualAssistantService::supportedModules() — a totally
    // unregistered module also returns enabled:false via emptyGuidance(),
    // just with every field blank. Confirmed empirically that this test
    // gave a false sense of coverage: real content is what actually proves
    // the fallback map entry exists.
    expect($response->json('what_to_do'))->not->toBeEmpty();
    expect($response->json('how_to_do'))->not->toBeEmpty();
});

test('the users directory is company-scoped and excludes the caller', function () {
    $me = actingAsUser('employee');
    $sameCompany = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $otherCompanyRecord = \App\Models\Company::factory()->create();
    $otherCompany = \App\Models\User::factory()->create(['company_id' => $otherCompanyRecord->id]);

    $response = $this->getJson('/api/v1/messaging/users');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($sameCompany->id);
    expect($ids)->not->toContain($otherCompany->id);
    expect($ids)->not->toContain($me->id);
});

test('message body is required and capped at 5000 characters', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $me->id]);
    $conversation->participants()->create(['user_id' => $me->id]);
    $conversation->participants()->create(['user_id' => $other->id]);

    $this->postJson("/api/v1/messaging/conversations/{$conversation->id}/messages", [])
        ->assertStatus(422);

    $this->postJson("/api/v1/messaging/conversations/{$conversation->id}/messages", [
        'body' => str_repeat('a', 5001),
    ])->assertStatus(422);
});
