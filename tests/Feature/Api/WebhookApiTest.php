<?php
declare(strict_types=1);
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can register a webhook', function () {
    actingAsUser('employee');
    $this->postJson('/api/v1/webhooks', [
        'url'    => 'https://example.com/hooks',
        'events' => ['crm.contact.created', 'invoice.paid'],
    ])->assertStatus(201)->assertJsonStructure(['id', 'url', 'events', 'secret']);
});

test('authenticated user can list own webhooks', function () {
    actingAsUser('employee');
    $this->getJson('/api/v1/webhooks')->assertOk()->assertJsonStructure(['data', 'total']);
});

test('authenticated user can update webhook', function () {
    $user = actingAsUser('employee');
    $webhook = Webhook::factory()->create(['user_id' => $user->id]);
    $this->putJson("/api/v1/webhooks/{$webhook->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('is_active', false);
});

test('authenticated user can delete webhook', function () {
    $user = actingAsUser('employee');
    $webhook = Webhook::factory()->create(['user_id' => $user->id]);
    $this->deleteJson("/api/v1/webhooks/{$webhook->id}")->assertNoContent();
});

test('can list available webhook events', function () {
    actingAsUser('employee');
    $this->getJson('/api/v1/webhooks-events')->assertOk()->assertJsonStructure(['events']);
});

test('openapi spec is accessible', function () {
    $this->getJson('/api/v1/openapi')->assertOk()->assertJsonStructure(['openapi', 'info', 'paths']);
});
