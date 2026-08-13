<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Services\SyncService;
use Modules\CRM\Models\Contact;

uses(RefreshDatabase::class);

function makeSyncService(): SyncService
{
    return app(SyncService::class);
}

function mutation(string $op, string $type, array $payload = [], ?string $entityId = null): array
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

// ── push() result structure ───────────────────────────────────────────────────

test('push returns applied/failed/conflicts keys', function () {
    $user = User::factory()->create();
    $result = makeSyncService()->push($user->id, [
        mutation('create', 'crm_contact', ['first_name' => 'Unit', 'last_name' => 'Test', 'email' => 'unit@test.com', 'type' => 'lead']),
    ]);

    expect($result)->toHaveKeys(['applied', 'failed', 'conflicts']);
    expect($result['applied'])->toBe(1);
    expect($result['failed'])->toBe(0);
});

test('push with empty mutations array returns zero counts', function () {
    $user = User::factory()->create();
    $result = makeSyncService()->push($user->id, []);

    expect($result['applied'])->toBe(0);
    expect($result['failed'])->toBe(0);
    expect($result['conflicts'])->toBeEmpty();
});

// ── Payload sanitization ──────────────────────────────────────────────────────

test('push strips id from create payload', function () {
    $user = User::factory()->create();

    makeSyncService()->push($user->id, [
        mutation('create', 'crm_contact', [
            'id'         => 9999,        // must be stripped
            'first_name' => 'Sanitize',
            'last_name'  => 'Test',
            'email'      => 'san@test.com',
            'type'       => 'lead',
        ]),
    ]);

    expect(Contact::find(9999))->toBeNull();
    expect(Contact::where('first_name', 'Sanitize')->exists())->toBeTrue();
});

test('push strips tenant_id and created_by from payload', function () {
    $user      = User::factory()->create();
    $otherUser = User::factory()->create();

    makeSyncService()->push($user->id, [
        mutation('create', 'crm_contact', [
            'first_name' => 'Blocked',
            'last_name'  => 'Fields',
            'email'      => 'blocked@test.com',
            'type'       => 'lead',
            'created_by' => $otherUser->id,  // must be stripped
            'tenant_id'  => 'other-tenant',  // must be stripped
        ]),
    ]);

    $contact = Contact::where('email', 'blocked@test.com')->first();
    expect($contact)->not->toBeNull();
    // created_by should not be set to the injected value
    expect((string) ($contact->created_by ?? ''))->not->toBe((string) $otherUser->id);
});

// ── Ownership enforcement ─────────────────────────────────────────────────────

test('update on own record succeeds', function () {
    $user    = User::factory()->create();
    $contact = Contact::factory()->create(['owner_id' => $user->id, 'first_name' => 'Before']);

    $result = makeSyncService()->push($user->id, [
        mutation('update', 'crm_contact', ['first_name' => 'After'], (string) $contact->id),
    ]);

    expect($result['applied'])->toBe(1);
    expect($contact->fresh()->first_name)->toBe('After');
});

test('update on another user record is rejected', function () {
    $owner    = User::factory()->create();
    $attacker = User::factory()->create();
    $contact  = Contact::factory()->create(['owner_id' => $owner->id, 'first_name' => 'Protected']);

    $result = makeSyncService()->push($attacker->id, [
        mutation('update', 'crm_contact', ['first_name' => 'Hacked'], (string) $contact->id),
    ]);

    expect($result['failed'])->toBe(1);
    expect($contact->fresh()->first_name)->toBe('Protected');
});

test('delete on own record succeeds', function () {
    $user    = User::factory()->create();
    $contact = Contact::factory()->create(['owner_id' => $user->id]);

    $result = makeSyncService()->push($user->id, [
        mutation('delete', 'crm_contact', [], (string) $contact->id),
    ]);

    expect($result['applied'])->toBe(1);
    expect(Contact::withTrashed()->find($contact->id)->deleted_at)->not->toBeNull();
});

test('delete on another user record is rejected', function () {
    $owner    = User::factory()->create();
    $attacker = User::factory()->create();
    $contact  = Contact::factory()->create(['owner_id' => $owner->id]);

    $result = makeSyncService()->push($attacker->id, [
        mutation('delete', 'crm_contact', [], (string) $contact->id),
    ]);

    expect($result['failed'])->toBe(1);
    expect(Contact::find($contact->id))->not->toBeNull();
});

// ── Error handling ────────────────────────────────────────────────────────────

test('unknown entity type is recorded as conflict', function () {
    $user = User::factory()->create();

    $result = makeSyncService()->push($user->id, [
        mutation('create', 'ghost_entity', ['foo' => 'bar']),
    ]);

    expect($result['failed'])->toBe(1);
    expect($result['conflicts'][0])->toHaveKey('error');
});

test('batch continues processing after one failed mutation', function () {
    $user = User::factory()->create();

    $result = makeSyncService()->push($user->id, [
        mutation('create', 'unknown', []),                                   // fails
        mutation('create', 'crm_contact', ['first_name' => 'OK', 'last_name' => 'User', 'email' => 'ok@test.com', 'type' => 'lead']), // succeeds
    ]);

    expect($result['applied'])->toBe(1);
    expect($result['failed'])->toBe(1);
});

// ── pull() ────────────────────────────────────────────────────────────────────

test('pull returns timestamp and changes keys', function () {
    $user = User::factory()->create();

    $result = makeSyncService()->pull($user->id, null);

    expect($result)->toHaveKeys(['timestamp', 'changes']);
    expect($result['changes'])->toBeArray();
});

test('pull only returns changes for the requesting user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Push mutation as userA
    makeSyncService()->push($userA->id, [
        mutation('create', 'crm_contact', ['first_name' => 'A', 'last_name' => 'User', 'email' => 'a@pull.com', 'type' => 'lead']),
    ]);

    // Pull as userB — should see no changes
    $result = makeSyncService()->pull($userB->id, now()->subMinute()->toISOString());

    expect($result['changes'])->toHaveCount(0);
});
