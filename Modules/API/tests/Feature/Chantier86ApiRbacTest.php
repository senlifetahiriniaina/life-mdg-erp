<?php

declare(strict_types=1);

use App\Models\User;

/**
 * Chantier 8.6 (API): ApiKeyPolicy/WebhookPolicy were fully written (admin/
 * super-admin/api-manager only for create/update/delete) but never registered
 * with Laravel's Gate (Modules-namespaced policies don't auto-discover) and
 * never called from ApiKeyController/WebhookController — any authenticated
 * user of any role could create/revoke API keys and webhooks. Locks in the
 * fix: a plain-role user (no api-manager/admin role) must be denied; an admin
 * must still work (already covered by the pre-existing ApiKeyTest.php, which
 * uses actingAsUser()'s admin default).
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

test('plain employee cannot create a webhook', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('sales-rep');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/api/webhooks', [
        'name' => 'Unauthorized Webhook',
        'url' => 'https://example.com/hook',
        'events' => ['order.created'],
    ]);

    $response->assertForbidden();
});

test('plain employee cannot update a webhook', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('sales-rep');
    $hook = \Modules\API\Models\ApiWebhook::create([
        'tenant_id' => 1,
        'name'      => 'Seed Hook',
        'url'       => 'https://example.com/hook',
        'events'    => ['order.created'],
    ]);

    $response = test()->actingAs($user, 'sanctum')->putJson("/api/v1/api/webhooks/{$hook->id}", [
        'name' => 'Renamed',
    ]);

    $response->assertForbidden();
});

test('plain employee cannot delete a webhook', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('sales-rep');
    $hook = \Modules\API\Models\ApiWebhook::create([
        'tenant_id' => 1,
        'name'      => 'Seed Hook',
        'url'       => 'https://example.com/hook',
        'events'    => ['order.created'],
    ]);

    $response = test()->actingAs($user, 'sanctum')->deleteJson("/api/v1/api/webhooks/{$hook->id}");

    $response->assertForbidden();
});

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
