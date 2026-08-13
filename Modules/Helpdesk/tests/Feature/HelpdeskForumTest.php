<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Helpdesk\Models\ForumPost;
use Modules\Helpdesk\Models\ForumReply;


it('can list forum posts', function () {
    $user = User::factory()->create();
    ForumPost::factory()->count(3)->create(['author_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/helpdesk/forum/posts')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('can create a forum post', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/helpdesk/forum/posts', [
            'title' => 'How do I export reports?',
            'content' => 'I cannot find the export button.',
        ])
        ->assertCreated()
        ->assertJsonPath('title', 'How do I export reports?')
        ->assertJsonPath('status', 'open');
});

it('can add a reply to a post', function () {
    $user = User::factory()->create();
    $post = ForumPost::factory()->create(['author_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/forum/posts/{$post->id}/replies", [
            'content' => 'You can find it in the Reports > Export menu.',
        ])
        ->assertCreated()
        ->assertJsonStructure(['id', 'content', 'author']);
});

it('can accept an answer', function () {
    $user = User::factory()->create();
    $post = ForumPost::factory()->create(['author_id' => $user->id]);
    $reply = ForumReply::factory()->create(['post_id' => $post->id, 'author_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/forum/posts/{$post->id}/accept-answer/{$reply->id}")
        ->assertOk()
        ->assertJsonPath('post_status', 'answered');

    expect($reply->fresh()->is_accepted)->toBeTrue();
    expect($post->fresh()->status)->toBe('answered');
});

it('can vote on a post', function () {
    $user = User::factory()->create();
    $post = ForumPost::factory()->create(['author_id' => $user->id, 'votes' => 0]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/helpdesk/forum/posts/{$post->id}/vote", ['direction' => 'up'])
        ->assertOk()
        ->assertJsonPath('votes', 1);
});
