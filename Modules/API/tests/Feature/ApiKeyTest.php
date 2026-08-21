<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can list api keys', function () {
    $user = actingAsUser();
    $this->getJson('/api/v1/api/keys')->assertOk();
});

test('user can create api key', function () {
    $user = actingAsUser();
    $this->postJson('/api/v1/api/keys', ['name' => 'Test Key'])
        ->assertCreated()
        ->assertJsonPath('data.note', 'Store this key — it will not be shown again');
});

test('user can view api key stats', function () {
    $user = actingAsUser();
    $this->getJson('/api/v1/api/logs/stats')->assertOk();
});

// Chantier 32.5: the "user can list webhooks"/"user can create webhook"
// tests that used to live here were removed along with the whole
// ApiWebhook/WebhookController subtree they exercised — confirmed dead
// (zero real delivery mechanism, test() faked success, index()/show()
// leaked the plaintext HMAC secret via a raw DB query) and fully
// superseded by the real, live App\Models\Webhook system this app already
// has at /api/v1/webhooks. See the api_webhooks drop migration's own
// docblock for the full rationale.
