<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Helpdesk\Models\Forum;
use Modules\Helpdesk\Models\ForumReply;
use Modules\Helpdesk\Models\ForumThread;
use Modules\Helpdesk\Models\ForumVote;
use Modules\Helpdesk\Services\ForumService;

// ── Forum listing & visibility ─────────────────────────────────────────────

it('lists public forums with thread counts', function () {
    Forum::factory()->count(3)->create(['is_public' => true]);
    Forum::factory()->create(['is_public' => false]);

    $this->getJson('/api/v1/helpdesk/forums')
        ->assertOk()
        ->assertJsonCount(3); // private forum excluded by default
});

it('can fetch a single forum with thread list', function () {
    $forum = Forum::factory()->create(['is_public' => true]);

    $this->getJson("/api/v1/helpdesk/forums/{$forum->id}")
        ->assertOk()
        ->assertJsonPath('id', $forum->id);
});

// ── Thread creation ────────────────────────────────────────────────────────

it('authenticated user can create a thread', function () {
    $user = User::factory()->create();
    $forum = Forum::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/forums/{$forum->id}/threads", [
            'title'   => 'How to configure SMTP?',
            'content' => 'I cannot find the SMTP settings page.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('title', 'How to configure SMTP?');
});

it('unauthenticated user cannot create a thread', function () {
    $forum = Forum::factory()->create();

    $this->postJson("/api/v1/helpdesk/forums/{$forum->id}/threads", [
        'title'   => 'Test',
        'content' => 'Test content',
    ])->assertUnauthorized();
});

it('cannot create a thread without required fields', function () {
    $user = User::factory()->create();
    $forum = Forum::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/forums/{$forum->id}/threads", ['title' => ''])
        ->assertUnprocessable();
});

// ── Thread detail & replies ────────────────────────────────────────────────

it('can view a thread with its replies', function () {
    $thread = ForumThread::factory()->create();
    ForumReply::factory()->count(3)->create(['thread_id' => $thread->id]);

    $this->getJson("/api/v1/helpdesk/threads/{$thread->id}")
        ->assertOk()
        ->assertJsonStructure(['id', 'title', 'replies']);
});

it('increments thread view count on each access', function () {
    $thread = ForumThread::factory()->create(['views' => 0]);

    $this->getJson("/api/v1/helpdesk/threads/{$thread->id}")->assertOk();
    $this->getJson("/api/v1/helpdesk/threads/{$thread->id}")->assertOk();

    expect($thread->fresh()->views)->toBe(2);
});

it('authenticated user can post a reply', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create(['status' => 'open']);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/threads/{$thread->id}/replies", [
            'content' => 'Here is the solution: go to Settings > Email.',
        ])
        ->assertCreated()
        ->assertJsonStructure(['id', 'content', 'author']);
});

it('cannot reply to a closed thread', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create(['status' => 'closed']);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/threads/{$thread->id}/replies", ['content' => 'Test'])
        ->assertUnprocessable();
});

// ── Accept answer ──────────────────────────────────────────────────────────

it('thread author can accept an answer', function () {
    $author = User::factory()->create();
    $thread = ForumThread::factory()->create(['author_id' => $author->id, 'is_answered' => false]);
    $reply = ForumReply::factory()->create(['thread_id' => $thread->id]);

    $this->actingAs($author, 'sanctum')
        ->postJson("/api/v1/helpdesk/threads/{$thread->id}/replies/{$reply->id}/accept-answer")
        ->assertOk()
        ->assertJsonPath('is_answered', true);

    expect($reply->fresh()->is_accepted_answer)->toBeTrue();
    expect($thread->fresh()->is_answered)->toBeTrue();
});

it('non-author cannot accept an answer', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $thread = ForumThread::factory()->create(['author_id' => $author->id]);
    $reply = ForumReply::factory()->create(['thread_id' => $thread->id]);

    $this->actingAs($other, 'sanctum')
        ->postJson("/api/v1/helpdesk/threads/{$thread->id}/replies/{$reply->id}/accept-answer")
        ->assertForbidden();
});

// ── Voting ─────────────────────────────────────────────────────────────────

it('user can upvote a thread', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create(['upvotes' => 0]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/threads/{$thread->id}/vote", ['direction' => 1])
        ->assertOk()
        ->assertJsonPath('upvotes', 1);
});

it('user can upvote a reply', function () {
    $user = User::factory()->create();
    $reply = ForumReply::factory()->create(['upvotes' => 0]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/replies/{$reply->id}/vote", ['direction' => 1])
        ->assertOk()
        ->assertJsonPath('upvotes', 1);
});

it('voting twice on the same entity toggles the vote off', function () {
    $user = User::factory()->create();
    $thread = ForumThread::factory()->create(['upvotes' => 0]);

    $service = app(ForumService::class);
    $service->voteOnThread($thread, $user->id, 1); // vote up
    $result = $service->voteOnThread($thread, $user->id, 1); // toggle off

    expect($result['action'])->toBe('removed');
    expect(ForumVote::where('votable_id', $thread->id)->where('user_id', $user->id)->count())->toBe(0);
});

// ── Search & popular threads ───────────────────────────────────────────────

it('can search forum threads by keyword', function () {
    ForumThread::factory()->create(['title' => 'SMTP configuration issue', 'content' => 'Email not working']);
    ForumThread::factory()->create(['title' => 'Invoice export', 'content' => 'Cannot export PDF']);

    $this->getJson('/api/v1/helpdesk/forums/search?q=SMTP')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns popular threads sorted by upvotes', function () {
    ForumThread::factory()->create(['upvotes' => 5]);
    ForumThread::factory()->create(['upvotes' => 15]);
    ForumThread::factory()->create(['upvotes' => 1]);

    $response = $this->getJson('/api/v1/helpdesk/forums/popular')
        ->assertOk();

    $upvotes = collect($response->json('data'))->pluck('upvotes')->toArray();
    expect($upvotes[0])->toBeGreaterThanOrEqual($upvotes[1]);
});

// ── ForumService unit tests ────────────────────────────────────────────────

it('ForumService::markThreadClosed sets status to closed', function () {
    $thread = ForumThread::factory()->create(['status' => 'open']);
    $service = app(ForumService::class);

    $closed = $service->markThreadClosed($thread);

    expect($closed->status)->toBe('closed');
});

it('ForumService::acceptAnswer unmarks previous accepted answer', function () {
    $thread = ForumThread::factory()->create(['is_answered' => false]);
    $first = ForumReply::factory()->create(['thread_id' => $thread->id, 'is_accepted_answer' => true]);
    $second = ForumReply::factory()->create(['thread_id' => $thread->id, 'is_accepted_answer' => false]);

    $service = app(ForumService::class);
    $service->acceptAnswer($thread, $second);

    expect($first->fresh()->is_accepted_answer)->toBeFalse();
    expect($second->fresh()->is_accepted_answer)->toBeTrue();
});
