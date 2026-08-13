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

test('user can list webhooks', function () {
    $user = actingAsUser();
    $this->getJson('/api/v1/api/webhooks')->assertOk();
});

test('user can create webhook', function () {
    $user = actingAsUser();
    $this->postJson('/api/v1/api/webhooks', [
        'name' => 'Order webhook',
        'url' => 'https://example.com/hook',
        'events' => ['order.created'],
    ])->assertCreated();
});
