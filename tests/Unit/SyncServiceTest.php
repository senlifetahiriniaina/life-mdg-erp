<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\SyncQueue;
use Modules\Core\Services\SyncService;
use Modules\CRM\Models\Contact;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function syncService(): SyncService
{
    return app(SyncService::class);
}

function unitSyncMutation(string $op, string $type, array $payload = [], ?string $entityId = null): array
{
    return [
        'id'               => (string) Str::uuid(),
        'entity_type'      => $type,
        'operation'        => $op,
        'payload'          => $payload,
        'entity_id'        => $entityId,
        'client_timestamp' => now()->toISOString(),
    ];
}

// ─── 1. Create mutation + SyncQueue record ────────────────────────────────────

it('applies a create mutation and records it in sync queue', function () {
    $user = User::factory()->create();

    $results = syncService()->push($user->id, [
        unitSyncMutation('create', 'crm_contact', [
            'first_name' => 'Sync',
            'last_name'  => 'User',
            'email'      => 'sync@example.com',
        ]),
    ]);

    // Push result indicates success
    expect($results['applied'])->toBe(1);
    expect($results['failed'])->toBe(0);

    // Exactly one SyncQueue row was written and marked 'applied'
    expect(SyncQueue::count())->toBe(1);
    expect(SyncQueue::first()->status)->toBe('applied');
    expect(SyncQueue::first()->entity_type)->toBe('crm_contact');

    // The Contact was actually created in the database
    expect(Contact::where('email', 'sync@example.com')->exists())->toBeTrue();
});

// ─── 2. Unknown entity type is rejected ───────────────────────────────────────

it('rejects an unknown entity type', function () {
    $user = User::factory()->create();

    $results = syncService()->push($user->id, [
        unitSyncMutation('create', 'unknown_model', ['foo' => 'bar']),
    ]);

    expect($results['failed'])->toBe(1);
    expect($results['applied'])->toBe(0);

    $conflict = $results['conflicts'][0];
    expect($conflict)->toHaveKey('error');
    expect($conflict['error'])->toContain('Unknown entity type');
});

// ─── 3. Unknown operation is rejected ────────────────────────────────────────

it('rejects an unknown operation', function () {
    $user = User::factory()->create();

    $results = syncService()->push($user->id, [
        unitSyncMutation('upsert', 'crm_contact', [
            'first_name' => 'Op',
            'last_name'  => 'Test',
            'email'      => 'op@example.com',
        ]),
    ]);

    expect($results['failed'])->toBe(1);
    expect($results['applied'])->toBe(0);

    // The service fails before creating the contact
    expect(Contact::where('email', 'op@example.com')->exists())->toBeFalse();
});

// ─── 4. Protected fields are blocked from payload ────────────────────────────

it('blocks protected fields from payload', function () {
    $user = User::factory()->create();

    $results = syncService()->push($user->id, [
        unitSyncMutation('create', 'crm_contact', [
            'id'         => 999,     // must be stripped
            'created_by' => 99,      // must be stripped
            'first_name' => 'Protected',
            'last_name'  => 'Fields',
            'email'      => 'protected@example.com',
        ]),
    ]);

    expect($results['applied'])->toBe(1);

    // The Contact must NOT have been forced to id=999
    expect(Contact::find(999))->toBeNull();

    // The Contact was created, but created_by was not set to the injected value
    $contact = Contact::where('email', 'protected@example.com')->firstOrFail();
    expect((string) ($contact->created_by ?? ''))->not->toBe('99');
});

// ─── 5. pull() returns applied SyncQueue records ─────────────────────────────

it('returns applied sync queue records on pull', function () {
    $user = User::factory()->create();

    // Seed two applied SyncQueue rows for this user
    SyncQueue::create([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'entity_type'      => 'crm_contact',
        'entity_id'        => '1',
        'operation'        => 'create',
        'payload'          => ['first_name' => 'A'],
        'status'           => 'applied',
        'client_timestamp' => now()->subMinutes(5),
    ]);

    SyncQueue::create([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'entity_type'      => 'crm_contact',
        'entity_id'        => '2',
        'operation'        => 'update',
        'payload'          => ['first_name' => 'B'],
        'status'           => 'applied',
        'client_timestamp' => now()->subMinutes(3),
    ]);

    // A 'processing' record — must NOT appear in pull results
    SyncQueue::create([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'entity_type'      => 'crm_contact',
        'entity_id'        => '3',
        'operation'        => 'delete',
        'payload'          => [],
        'status'           => 'processing',
        'client_timestamp' => now()->subMinutes(1),
    ]);

    $result = syncService()->pull($user->id, now()->subHour()->toISOString());

    expect($result)->toHaveKeys(['timestamp', 'changes']);
    expect($result['changes'])->toHaveCount(2);

    $entityTypes = array_column($result['changes'], 'entity_type');
    expect($entityTypes)->each->toBe('crm_contact');
});

// ─── 6. Unauthorized update on owned entity is blocked ───────────────────────

it('prevents unauthorized update on owned entity', function () {
    $owner   = User::factory()->create();
    $userB   = User::factory()->create();
    $contact = Contact::factory()->create([
        'owner_id'   => $owner->id,
        'first_name' => 'Original',
    ]);

    // userB tries to update a contact owned by $owner
    $results = syncService()->push($userB->id, [
        unitSyncMutation('update', 'crm_contact', ['first_name' => 'Hijacked'], (string) $contact->id),
    ]);

    expect($results['failed'])->toBe(1);
    expect($results['applied'])->toBe(0);

    $error = $results['conflicts'][0]['error'];
    expect($error)->toContain('Unauthorized');

    // Contact must remain unchanged
    expect($contact->fresh()->first_name)->toBe('Original');
});
