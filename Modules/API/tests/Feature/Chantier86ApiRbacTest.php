<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Chantier 8.6 (API): ApiKeyPolicy was fully written (admin/super-admin only
 * for create/update/delete) but never registered with Laravel's Gate
 * (Modules-namespaced policies don't auto-discover) and never called from
 * ApiKeyController — any authenticated user of any role could create/revoke
 * API keys. Locks in the fix: a plain-role user (no admin role) must be
 * denied; an admin must still work (already covered by the pre-existing
 * ApiKeyTest.php, which uses actingAsUser()'s admin default).
 *
 * The equivalent webhook coverage that used to live in this file was
 * removed at Chantier 32.5 along with the whole ApiWebhook/WebhookController/
 * WebhookPolicy subtree — see the api_webhooks drop migration's own
 * docblock for the full rationale (confirmed dead/insecure, superseded by
 * the real, live App\Models\Webhook system at /api/v1/webhooks).
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('plain employee cannot create an api key', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('sales-rep');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/api/keys', [
        'name' => 'Unauthorized Key',
    ]);

    $response->assertForbidden();
});

test('plain employee cannot revoke an api key', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('sales-rep');
    // Modules\API\Models\ApiKey/ApiWebhook have no factory anywhere in the repo
    // (the only ApiKeyFactory in this app belongs to the unrelated
    // Modules\Core\Models\ApiKey / core_api_keys secrets-vault model) — created
    // directly against the real $fillable/schema instead.
    $key = \Modules\API\Models\ApiKey::create([
        'tenant_id'   => 1,
        'name'        => 'Seed Key',
        'key_hash'    => hash('sha256', 'seed-key'),
        'key_prefix'  => 'seedkey1',
    ]);

    $response = test()->actingAs($user, 'sanctum')->deleteJson("/api/v1/api/keys/{$key->id}/revoke");

    $response->assertForbidden();
});

// Chantier 32.5: the 4 "plain employee cannot create/update/delete a
// webhook" tests that used to live here were removed along with the whole
// ApiWebhook/WebhookController/WebhookPolicy subtree they exercised —
// confirmed dead (zero real delivery mechanism, test() faked success,
// index()/show() leaked the plaintext HMAC secret via a raw DB query) and
// fully superseded by the real, live App\Models\Webhook/App\Policies\
// WebhookPolicy system this app already has at /api/v1/webhooks. See the
// api_webhooks drop migration's own docblock for the full rationale.

test('admin can create and revoke an api key', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');

    $created = test()->actingAs($user, 'sanctum')->postJson('/api/v1/api/keys', [
        'name' => 'Authorized Key',
    ]);
    $created->assertCreated();

    $keyId = $created->json('data.id');

    test()->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/api/keys/{$keyId}/revoke")
        ->assertOk();
});
