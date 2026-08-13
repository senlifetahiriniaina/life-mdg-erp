<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CRM\Models\Contact;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function syncMutation(string $operation, string $entityType, array $payload = [], ?string $entityId = null): array
{
    return [
        'id'               => (string) Str::uuid(),
        'entity_type'      => $entityType,
        'operation'        => $operation,
        'payload'          => $payload,
        'entity_id'        => $entityId,
        'client_timestamp' => now()->toISOString(),
    ];
}

// ── Authentication ─────────────────────────────────────────────────────────────

test('unauthenticated user cannot push mutations', function () {
    $this->postJson('/api/v1/sync/push', ['mutations' => []])
        ->assertUnauthorized();
});

test('unauthenticated user cannot pull changes', function () {
    $this->getJson('/api/v1/sync/pull')
        ->assertUnauthorized();
});

// ── Validation ─────────────────────────────────────────────────────────────────

test('push rejects empty mutations array', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/sync/push', [])
        ->assertUnprocessable();
});

test('push rejects mutations missing required fields', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/sync/push', [
            'mutations' => [
                ['operation' => 'create'], // missing id, entity_type, payload, client_timestamp
            ],
        ])
        ->assertUnprocessable();
});

test('push rejects mutations with invalid operation', function () {
     $user = actingAsUser('employee');

    $mutation = syncMutation('upsert', 'crm_contact', ['first_name' => 'Test']); // 'upsert' is not valid

    $response = $this
        ->postJson('/api/v1/sync/push', ['mutations' => [$mutation]])
        ->assertUnprocessable();
});

// ── Push — create ─────────────────────────────────────────────────────────────

test('push create mutation for crm_contact inserts record', function () {
     $user = actingAsUser('employee');

    $mutation = syncMutation('create', 'crm_contact', [
        'first_name' => 'Sync',
        'last_name'  => 'Contact',
        'email'      => 'sync@test.com',
        'type'       => 'customer',
    ]);
        $response = $this
        ->postJson('/api/v1/sync/push', ['mutations' => [$mutation]])
        ->assertOk()
        ->assertJsonStructure(['applied', 'failed', 'conflicts']);

    expect($response->json('applied'))->toBe(1);
    expect($response->json('failed'))->toBe(0);
    $this->assertDatabaseHas('crm_contacts', ['first_name' => 'Sync', 'email' => 'sync@test.com']);
});

test('push strips blocked fields from payload', function () {
     $user = actingAsUser('employee');
    $otherUser = User::factory()->create();

    $mutation = syncMutation('create', 'crm_contact', [
        'first_name' => 'Hijack',
        'last_name'  => 'Test',
        'email'      => 'hijack@test.com',
        'type'       => 'lead',
        'user_id'    => $otherUser->id,   // BLOCKED
        'created_by' => $otherUser->id,   // BLOCKED
        'id'         => 9999,             // BLOCKED
    ]);
        $response = $this
        ->postJson('/api/v1/sync/push', ['mutations' => [$mutation]])
        ->assertOk();

    expect($response->json('applied'))->toBe(1);
    $this->assertDatabaseMissing('crm_contacts', ['id' => 9999]);
});

// ── Push — update ─────────────────────────────────────────────────────────────

test('push update mutation for own crm_contact succeeds', function () {
     $user = actingAsUser('employee');
    $contact = Contact::factory()->create(['owner_id' => $user->id, 'first_name' => 'Original']);

    $mutation = syncMutation('update', 'crm_contact', ['first_name' => 'Updated'], (string) $contact->id);
        $response = $this
        ->postJson('/api/v1/sync/push', ['mutations' => [$mutation]])
        ->assertOk();

    expect($response->json('applied'))->toBe(1);
    $this->assertDatabaseHas('crm_contacts', ['id' => $contact->id, 'first_name' => 'Updated']);
});

test('push update mutation on another user contact is rejected', function () {
    $owner    = User::factory()->create();
    $attacker = User::factory()->create();
    $contact  = Contact::factory()->create(['owner_id' => $owner->id, 'first_name' => 'Protected']);

    $mutation = syncMutation('update', 'crm_contact', ['first_name' => 'Stolen'], (string) $contact->id);

    $response = $this->actingAs($attacker, 'sanctum')
        ->postJson('/api/v1/sync/push', ['mutations' => [$mutation]])
        ->assertOk();

    expect($response->json('failed'))->toBe(1);
    expect($response->json('applied'))->toBe(0);
    $this->assertDatabaseMissing('crm_contacts', ['id' => $contact->id, 'first_name' => 'Stolen']);
});

// ── Push — delete ─────────────────────────────────────────────────────────────

test('push delete mutation on own crm_contact succeeds', function () {
     $user = actingAsUser('employee');
    $contact = Contact::factory()->create(['owner_id' => $user->id]);

    $mutation = syncMutation('delete', 'crm_contact', [], (string) $contact->id);
        $response = $this
        ->postJson('/api/v1/sync/push', ['mutations' => [$mutation]])
        ->assertOk();

    expect($response->json('applied'))->toBe(1);
    $this->assertSoftDeleted('crm_contacts', ['id' => $contact->id]);
});

// ── Push — unknown entity type ────────────────────────────────────────────────

test('push with unknown entity_type reports failure in result', function () {
     $user = actingAsUser('employee');

    $mutation = syncMutation('create', 'unknown_entity', ['foo' => 'bar']);
        $response = $this
        ->postJson('/api/v1/sync/push', ['mutations' => [$mutation]])
        ->assertOk();

    expect($response->json('failed'))->toBe(1);
    expect($response->json('conflicts'))->toHaveCount(1);
});

// ── Push — batch ──────────────────────────────────────────────────────────────

test('push processes multiple mutations in a single batch', function () {
     $user = actingAsUser('employee');

    $mutations = [
        syncMutation('create', 'crm_contact', ['first_name' => 'Batch', 'last_name' => 'A', 'email' => 'a@batch.com', 'type' => 'lead']),
        syncMutation('create', 'crm_contact', ['first_name' => 'Batch', 'last_name' => 'B', 'email' => 'b@batch.com', 'type' => 'lead']),
        syncMutation('create', 'unknown_entity', ['foo' => 'bar']), // will fail
    ];
        $response = $this
        ->postJson('/api/v1/sync/push', ['mutations' => $mutations])
        ->assertOk();

    expect($response->json('applied'))->toBe(2);
    expect($response->json('failed'))->toBe(1);
    $this->assertDatabaseHas('crm_contacts', ['last_name' => 'A']);
    $this->assertDatabaseHas('crm_contacts', ['last_name' => 'B']);
});

// ── Pull ──────────────────────────────────────────────────────────────────────

test('pull returns changes since timestamp', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/sync/pull?last_sync_at=2020-01-01T00:00:00Z')
        ->assertOk()
        ->assertJsonStructure(['timestamp', 'changes']);

    expect($response->json('changes'))->toBeArray();
});

test('pull with no last_sync_at defaults to 7 days back', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/sync/pull')
        ->assertOk()
        ->assertJsonStructure(['timestamp', 'changes']);
});

test('pull with invalid date returns validation error', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/sync/pull?last_sync_at=not-a-date')
        ->assertUnprocessable();
});

test('pull only returns changes for the authenticated user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Push a mutation as userA
    $this->actingAs($userA, 'sanctum')->postJson('/api/v1/sync/push', [
        'mutations' => [
            syncMutation('create', 'crm_contact', ['first_name' => 'UserA', 'last_name' => 'Contact', 'email' => 'ua@test.com', 'type' => 'lead']),
        ],
    ]);

    // UserB pulls — must not see userA's sync records
    $response = $this->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/sync/pull?last_sync_at=' . now()->subMinute()->toISOString())
        ->assertOk();

    expect($response->json('changes'))->toHaveCount(0);
});
