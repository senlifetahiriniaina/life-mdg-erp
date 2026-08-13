<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;

uses(RefreshDatabase::class);

// ── Auth ──────────────────────────────────────────────────────────────────────

test('notifications index requires authentication', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});

test('unread count requires authentication', function () {
    $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('notifications index returns paginated list', function () {
     $user = actingAsUser('employee');

    DatabaseNotification::create([
        'id'              => \Illuminate\Support\Str::uuid(),
        'type'            => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id'   => $user->id,
        'data'            => json_encode(['message' => 'Hello']),
        'read_at'         => null,
    ]);
        $response = $this
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('unread_only filter returns only unread notifications', function () {
     $user = actingAsUser('employee');

    DatabaseNotification::create([
        'id'              => \Illuminate\Support\Str::uuid(),
        'type'            => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id'   => $user->id,
        'data'            => json_encode(['message' => 'Unread']),
        'read_at'         => null,
    ]);

    DatabaseNotification::create([
        'id'              => \Illuminate\Support\Str::uuid(),
        'type'            => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id'   => $user->id,
        'data'            => json_encode(['message' => 'Read']),
        'read_at'         => now(),
    ]);
    $response = $this
        ->getJson('/api/v1/notifications?unread_only=1')
        ->assertOk()
        ->json();

    expect($response['total'])->toBe(1);
});

// ── Unread count ──────────────────────────────────────────────────────────────

test('unread count returns correct number', function () {
     $user = actingAsUser('employee');

    foreach (range(1, 3) as $i) {
        DatabaseNotification::create([
            'id'              => \Illuminate\Support\Str::uuid(),
            'type'            => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id'   => $user->id,
            'data'            => json_encode(['message' => "Notif {$i}"]),
            'read_at'         => null,
        ]);
    }

        $response = $this
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('count', 3);
});

test('unread count ignores read notifications', function () {
     $user = actingAsUser('employee');

    DatabaseNotification::create([
        'id'              => \Illuminate\Support\Str::uuid(),
        'type'            => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id'   => $user->id,
        'data'            => json_encode(['message' => 'Already read']),
        'read_at'         => now()->subHour(),
    ]);
        $response = $this
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('count', 0);
});

// ── Mark read ─────────────────────────────────────────────────────────────────

test('mark single notification as read', function () {
     $user = actingAsUser('employee');
    $id   = (string) \Illuminate\Support\Str::uuid();

    DatabaseNotification::create([
        'id'              => $id,
        'type'            => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id'   => $user->id,
        'data'            => json_encode(['message' => 'Mark me']),
        'read_at'         => null,
    ]);
        $response = $this
        ->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('message', 'Notification marked as read.');

    $this->assertDatabaseHas('notifications', ['id' => $id])
        ->assertNotNull(DatabaseNotification::find($id)?->read_at);
});

test('mark read returns 404 for unknown notification id', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/notifications/non-existent-id/read')
        ->assertNotFound();
});

test('mark all notifications as read', function () {
     $user = actingAsUser('employee');

    foreach (range(1, 2) as $i) {
        DatabaseNotification::create([
            'id'              => \Illuminate\Support\Str::uuid(),
            'type'            => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id'   => $user->id,
            'data'            => json_encode(['message' => "Notif {$i}"]),
            'read_at'         => null,
        ]);
    }

        $response = $this
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk();
        $response = $this
        ->getJson('/api/v1/notifications/unread-count')
        ->assertJsonPath('count', 0);
});

// ── Delete ────────────────────────────────────────────────────────────────────

test('delete notification removes it', function () {
     $user = actingAsUser('employee');
    $id   = (string) \Illuminate\Support\Str::uuid();

    DatabaseNotification::create([
        'id'              => $id,
        'type'            => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id'   => $user->id,
        'data'            => json_encode(['message' => 'Delete me']),
        'read_at'         => null,
    ]);
        $response = $this
        ->deleteJson("/api/v1/notifications/{$id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('notifications', ['id' => $id]);
});

test('cannot access another user notifications', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $id    = (string) \Illuminate\Support\Str::uuid();

    DatabaseNotification::create([
        'id'              => $id,
        'type'            => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id'   => $user2->id,
        'data'            => json_encode(['message' => 'Private']),
        'read_at'         => null,
    ]);

    $this->actingAs($user1, 'sanctum')
        ->postJson("/api/v1/notifications/{$id}/read")
        ->assertNotFound();
});
