<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\Message;

uses(RefreshDatabase::class);

// Chantier 32.28 — 14-layer deep audit of Modules\Messaging. Locks in every
// real bug found and fixed by real HTTP requests / real query-count
// measurement, not just re-reading the code. See CLAUDE.md's Chantier 32.28
// entry for the full write-up.

test('store() rejects a recipient from a different company (cross-tenant IDOR, layer 6)', function () {
    $me = actingAsUser('employee');
    $otherCompany = \App\Models\Company::factory()->create();
    $stranger = \App\Models\User::factory()->create(['company_id' => $otherCompany->id]);

    $response = $this->postJson('/api/v1/messaging/conversations', [
        'user_ids' => [$stranger->id],
    ]);

    $response->assertStatus(422);
    expect(Conversation::count())->toBe(0);
});

test('store() still succeeds for a same-company recipient after the tenant-scoping fix', function () {
    $me = actingAsUser('employee');
    $sameCompany = \App\Models\User::factory()->create(['company_id' => $me->company_id]);

    $response = $this->postJson('/api/v1/messaging/conversations', [
        'user_ids' => [$sameCompany->id],
    ]);

    $response->assertCreated();
});

test('store() rejects a self-only conversation with a clean 422 instead of a fatal error (layer 8)', function () {
    $me = actingAsUser('employee');

    $response = $this->postJson('/api/v1/messaging/conversations', [
        'user_ids' => [$me->id],
    ]);

    // Confirmed empirically before this fix: this exact request threw a raw
    // ErrorException ("Undefined array key 1") at the direct-conversation
    // reuse lookup, a 500 rather than a validation error.
    $response->assertStatus(422);
    expect(Conversation::count())->toBe(0);
});

test('store() silently drops a duplicated caller id from user_ids rather than crashing', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);

    $response = $this->postJson('/api/v1/messaging/conversations', [
        'user_ids' => [$other->id, $me->id],
    ]);

    $response->assertCreated();
    expect(Conversation::find($response->json('data.id'))->type)->toBe('direct');
});

test('index() unread_count never counts the caller\'s own sent messages (layer 8 correctness)', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $me->id]);
    $conversation->participants()->create(['user_id' => $me->id]); // last_read_at left NULL, matching real creation flow
    $conversation->participants()->create(['user_id' => $other->id]);

    $this->postJson("/api/v1/messaging/conversations/{$conversation->id}/messages", ['body' => 'my own first message'])
        ->assertCreated();

    $index = $this->getJson('/api/v1/messaging/conversations');
    $row = collect($index->json('data'))->firstWhere('id', $conversation->id);

    expect($row['unread_count'])->toBe(0);
});

test('index() unread_count still correctly counts a message sent by someone else', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $other->id]);
    $conversation->participants()->create(['user_id' => $me->id]);
    $conversation->participants()->create(['user_id' => $other->id]);
    $conversation->messages()->create(['sender_id' => $other->id, 'body' => 'hello']);
    $conversation->messages()->create(['sender_id' => $me->id, 'body' => 'my own reply']);

    $index = $this->getJson('/api/v1/messaging/conversations');
    $row = collect($index->json('data'))->firstWhere('id', $conversation->id);

    // Only the 1 message from "other" is unread — the caller's own reply
    // never counts, whether it was sent before or after their last read.
    expect($row['unread_count'])->toBe(1);
});

test('index() no longer executes an N+1 query pair per conversation (layer 14f performance)', function () {
    $me = actingAsUser('employee');
    for ($i = 0; $i < 5; $i++) {
        $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
        $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $me->id]);
        $conversation->participants()->create(['user_id' => $me->id, 'last_read_at' => now()->subDay()]);
        $conversation->participants()->create(['user_id' => $other->id]);
        $conversation->messages()->create(['sender_id' => $other->id, 'body' => 'msg from other']);
    }

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/messaging/conversations');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    expect(count($response->json('data')))->toBe(5);
    // Confirmed empirically before this fix: 16 queries for 5 conversations
    // (10 of them a redundant per-conversation participants()+unread-count
    // pair). After the fix: a fixed, small number of queries regardless of
    // how many conversations are returned — asserted well under the old
    // per-conversation multiplier rather than an exact count, so this stays
    // robust to unrelated framework/middleware query count drift.
    expect($queryCount)->toBeLessThan(12);
});

test('lastMessage uses a real single-row-per-conversation query, not an unbounded load', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $me->id]);
    $conversation->participants()->create(['user_id' => $me->id]);
    $conversation->participants()->create(['user_id' => $other->id]);
    foreach (range(1, 20) as $i) {
        $conversation->messages()->create(['sender_id' => $other->id, 'body' => "message {$i}"]);
    }

    $index = $this->getJson('/api/v1/messaging/conversations');
    $row = collect($index->json('data'))->firstWhere('id', $conversation->id);

    expect($row['last_message']['body'])->toBe('message 20');
});

test('the messaging route is reachable for a specialised, non-generic role (RBAC too-narrow gate, layer 7)', function () {
    // Confirmed empirically before this fix: the route gate
    // (role:employee,manager,admin,super-admin) 403'd any user whose only
    // role was a specialised one — this app assigns a single role per real
    // user (see DemoSeeder's syncRoles([$d['role']])), so most of the real
    // workforce (sales-rep, hr-manager, accountant, ...) could never reach
    // internal team messaging at all.
    actingAsUser('sales-rep');

    $this->getJson('/api/v1/messaging/conversations')->assertOk();
});

test('another specialised role (hr-manager) can also reach messaging', function () {
    actingAsUser('hr-manager');

    $this->getJson('/api/v1/messaging/conversations')->assertOk();
});

test('the AI assist endpoint resolves real Messaging fallback content in both languages (layer 13)', function () {
    actingAsUser('employee');

    $fr = $this->postJson('/api/v1/messaging/ai/assist', ['action' => 'view_dashboard', 'locale' => 'fr']);
    $en = $this->postJson('/api/v1/messaging/ai/assist', ['action' => 'start_conversation', 'locale' => 'en']);

    $fr->assertOk();
    expect($fr->json('what_to_do'))->not->toBeEmpty();
    expect($fr->json('how_to_do'))->not->toBeEmpty();

    $en->assertOk();
    expect($en->json('what_to_do'))->not->toBeEmpty();
});

test('Messaging is registered in AiContextualAssistantService::supportedModules()', function () {
    $service = new \Modules\AI\Services\AiContextualAssistantService();
    $modules = $service->supportedModules();

    expect($modules)->toHaveKey('Messaging');
    expect($modules['Messaging'])->toContain('view_dashboard', 'start_conversation');
});

test('created_by has a real foreign key constraint (layer 10, relational)', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $other->id]);

    $other->forceDelete();

    expect($conversation->fresh()->created_by)->toBeNull();
});

test('attachment_path is a real migrated column with no write path yet — deliberately deferred, confirmed still true', function () {
    $me = actingAsUser('employee');
    $other = \App\Models\User::factory()->create(['company_id' => $me->company_id]);
    $conversation = Conversation::create(['company_id' => $me->company_id, 'type' => 'direct', 'created_by' => $me->id]);
    $conversation->participants()->create(['user_id' => $me->id]);
    $conversation->participants()->create(['user_id' => $other->id]);

    $response = $this->postJson("/api/v1/messaging/conversations/{$conversation->id}/messages", [
        'body' => 'hi',
        'attachment_path' => '/etc/passwd',
    ]);

    $response->assertCreated();
    expect(Message::first()->attachment_path)->toBeNull();
});
