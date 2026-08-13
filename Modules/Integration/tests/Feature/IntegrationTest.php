<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Models\SyncLog;
use Modules\Integration\Models\WebhookEndpoint;
use Modules\Integration\Services\IntegrationService;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeConnector(array $overrides = []): IntegrationConnector
{
    return IntegrationConnector::create(array_merge([
        'tenant_id'     => 1,
        'name'          => 'Test Connector',
        'slug'          => 'test-connector',
        'provider_type' => 'webhook',
        'config'        => null,
        'status'        => 'inactive',
        'created_by'    => 1,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Test 1 — Service can be instantiated
// ---------------------------------------------------------------------------

test('IntegrationService can be resolved from container', function () {
    $service = app(IntegrationService::class);
    expect($service)->toBeInstanceOf(IntegrationService::class);
});

// ---------------------------------------------------------------------------
// Test 2 — createConnector stores record with correct defaults
// ---------------------------------------------------------------------------

test('createConnector creates inactive connector with slug', function () {
    $service = app(IntegrationService::class);

    $connector = $service->createConnector([
        'tenant_id'     => 1,
        'name'          => 'Shopify Webhook',
        'provider_type' => 'webhook',
        'config'        => ['api_url' => 'https://shopify.example.com'],
        'created_by'    => 1,
    ]);

    expect($connector)->toBeInstanceOf(IntegrationConnector::class)
        ->and($connector->status)->toBe('inactive')
        ->and($connector->slug)->toBe('shopify-webhook')
        ->and($connector->tenant_id)->toBe(1);
});

// ---------------------------------------------------------------------------
// Test 3 — Slug is auto-generated from name
// ---------------------------------------------------------------------------

test('createConnector generates slug from name automatically', function () {
    $service = app(IntegrationService::class);

    $connector = $service->createConnector([
        'tenant_id'     => 2,
        'name'          => 'My API Integration',
        'provider_type' => 'api_key',
        'created_by'    => 1,
    ]);

    expect($connector->slug)->toBe('my-api-integration');
});

// ---------------------------------------------------------------------------
// Test 4 — Duplicate slug within same tenant gets suffix
// ---------------------------------------------------------------------------

test('createConnector adds suffix when slug already exists for tenant', function () {
    $service = app(IntegrationService::class);

    $service->createConnector([
        'tenant_id'     => 3,
        'name'          => 'CRM Sync',
        'provider_type' => 'oauth2',
        'created_by'    => 1,
    ]);

    $second = $service->createConnector([
        'tenant_id'     => 3,
        'name'          => 'CRM Sync',
        'provider_type' => 'oauth2',
        'created_by'    => 1,
    ]);

    expect($second->slug)->toBe('crm-sync-1');
});

// ---------------------------------------------------------------------------
// Test 5 — activateConnector sets status to active
// ---------------------------------------------------------------------------

test('activateConnector transitions status to active', function () {
    $service = app(IntegrationService::class);
    $connector = makeConnector();

    expect($connector->status)->toBe('inactive');

    $activated = $service->activateConnector($connector);

    expect($activated->status)->toBe('active')
        ->and($activated->error_message)->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 6 — activateConnector clears existing error_message
// ---------------------------------------------------------------------------

test('activateConnector clears previous error_message', function () {
    $service = app(IntegrationService::class);
    $connector = makeConnector(['status' => 'error', 'error_message' => 'Previous failure']);

    $activated = $service->activateConnector($connector);

    expect($activated->error_message)->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 7 — dispatchWebhook returns SyncLog on success
// ---------------------------------------------------------------------------

test('dispatchWebhook returns SyncLog with success status', function () {
    Http::fake([
        'https://hooks.example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $service = app(IntegrationService::class);
    $connector = makeConnector(['status' => 'active']);

    WebhookEndpoint::create([
        'connector_id'    => $connector->id,
        'url'             => 'https://hooks.example.com/receive',
        'method'          => 'POST',
        'is_active'       => true,
        'retry_attempts'  => 3,
        'timeout_seconds' => 10,
    ]);

    $log = $service->dispatchWebhook($connector, ['event' => 'order.created', 'id' => 42]);

    expect($log)->toBeInstanceOf(SyncLog::class)
        ->and($log->status)->toBe('success')
        ->and($log->direction)->toBe('outbound')
        ->and($log->records_processed)->toBe(1)
        ->and($log->records_failed)->toBe(0);
});

// ---------------------------------------------------------------------------
// Test 8 — dispatchWebhook marks connector error when all endpoints fail
// ---------------------------------------------------------------------------

test('dispatchWebhook sets connector status to error when all endpoints fail', function () {
    Http::fake([
        'https://dead.example.com/*' => Http::response('Server Error', 500),
    ]);

    $service = app(IntegrationService::class);
    $connector = makeConnector(['status' => 'active']);

    WebhookEndpoint::create([
        'connector_id'    => $connector->id,
        'url'             => 'https://dead.example.com/hook',
        'method'          => 'POST',
        'is_active'       => true,
        'retry_attempts'  => 1,
        'timeout_seconds' => 5,
    ]);

    $log = $service->dispatchWebhook($connector, ['event' => 'test']);

    expect($log->status)->toBe('failed')
        ->and($log->records_failed)->toBe(1);

    $connector->refresh();
    expect($connector->status)->toBe('error');
});

// ---------------------------------------------------------------------------
// Test 9 — dispatchWebhook with HMAC secret sends signature header
// ---------------------------------------------------------------------------

test('dispatchWebhook sends HMAC signature header when secret_key is set', function () {
    Http::fake([
        'https://signed.example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $service = app(IntegrationService::class);
    $connector = makeConnector(['status' => 'active']);

    WebhookEndpoint::create([
        'connector_id'    => $connector->id,
        'url'             => 'https://signed.example.com/hook',
        'method'          => 'POST',
        'secret_key'      => 'super-secret',
        'is_active'       => true,
        'retry_attempts'  => 1,
        'timeout_seconds' => 5,
    ]);

    $log = $service->dispatchWebhook($connector, ['event' => 'signed']);

    expect($log->status)->toBe('success');

    Http::assertSent(function ($request) {
        return str_starts_with($request->header('X-WideHalo-Signature')[0] ?? '', 'sha256=');
    });
});

// ---------------------------------------------------------------------------
// Test 10 — dispatchWebhook skips inactive endpoints
// ---------------------------------------------------------------------------

test('dispatchWebhook skips inactive webhook endpoints', function () {
    Http::fake();

    $service = app(IntegrationService::class);
    $connector = makeConnector(['status' => 'active']);

    WebhookEndpoint::create([
        'connector_id'    => $connector->id,
        'url'             => 'https://hooks.example.com/inactive',
        'method'          => 'POST',
        'is_active'       => false,
        'retry_attempts'  => 1,
        'timeout_seconds' => 5,
    ]);

    $log = $service->dispatchWebhook($connector, ['event' => 'test']);

    // No active endpoints → failed status (0 processed, 0 failed but still 'failed')
    expect($log->records_processed)->toBe(0);
    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Test 11 — logSync records inbound log
// ---------------------------------------------------------------------------

test('logSync creates inbound SyncLog entry', function () {
    $service = app(IntegrationService::class);
    $connector = makeConnector();

    $log = $service->logSync($connector, 'inbound', [
        'status'            => 'success',
        'records_processed' => 50,
        'records_failed'    => 0,
        'payload_size'      => 1024,
    ]);

    expect($log)->toBeInstanceOf(SyncLog::class)
        ->and($log->direction)->toBe('inbound')
        ->and($log->status)->toBe('success')
        ->and($log->records_processed)->toBe(50)
        ->and($log->payload_size)->toBe(1024);
});

// ---------------------------------------------------------------------------
// Test 12 — logSync updates connector last_sync_at
// ---------------------------------------------------------------------------

test('logSync updates connector last_sync_at timestamp', function () {
    $service = app(IntegrationService::class);
    $connector = makeConnector();

    expect($connector->last_sync_at)->toBeNull();

    $service->logSync($connector, 'inbound', ['status' => 'success']);

    $connector->refresh();
    expect($connector->last_sync_at)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 13 — getConnectorStats returns correct counts
// ---------------------------------------------------------------------------

test('getConnectorStats returns aggregated tenant stats', function () {
    $service = app(IntegrationService::class);

    IntegrationConnector::create([
        'tenant_id' => 10, 'name' => 'C1', 'slug' => 'c1',
        'provider_type' => 'webhook', 'status' => 'active', 'created_by' => 1,
    ]);
    IntegrationConnector::create([
        'tenant_id' => 10, 'name' => 'C2', 'slug' => 'c2',
        'provider_type' => 'api_key', 'status' => 'inactive', 'created_by' => 1,
    ]);
    IntegrationConnector::create([
        'tenant_id' => 10, 'name' => 'C3', 'slug' => 'c3',
        'provider_type' => 'webhook', 'status' => 'error', 'created_by' => 1,
    ]);

    $stats = $service->getConnectorStats(10);

    expect($stats['total'])->toBe(3)
        ->and($stats['active'])->toBe(1)
        ->and($stats['inactive'])->toBe(1)
        ->and($stats['error'])->toBe(1)
        ->and($stats['by_provider']['webhook'])->toBe(2)
        ->and($stats['by_provider']['api_key'])->toBe(1);
});

// ---------------------------------------------------------------------------
// Test 14 — getConnectorStats sync counts
// ---------------------------------------------------------------------------

test('getConnectorStats counts sync logs correctly', function () {
    $service = app(IntegrationService::class);
    $connector = makeConnector(['tenant_id' => 20, 'slug' => 'connector-20']);

    SyncLog::create([
        'connector_id' => $connector->id, 'tenant_id' => 20,
        'direction' => 'outbound', 'status' => 'success',
        'records_processed' => 10, 'started_at' => now(),
    ]);
    SyncLog::create([
        'connector_id' => $connector->id, 'tenant_id' => 20,
        'direction' => 'outbound', 'status' => 'failed',
        'records_processed' => 0, 'started_at' => now(),
    ]);

    $stats = $service->getConnectorStats(20);

    expect($stats['total_syncs'])->toBe(2)
        ->and($stats['successful_syncs'])->toBe(1)
        ->and($stats['failed_syncs'])->toBe(1);
});

// ---------------------------------------------------------------------------
// Test 15 — IntegrationConnector model scopes
// ---------------------------------------------------------------------------

test('IntegrationConnector active scope filters by status', function () {
    makeConnector(['tenant_id' => 30, 'slug' => 'active-c', 'status' => 'active']);
    makeConnector(['tenant_id' => 30, 'slug' => 'inactive-c', 'status' => 'inactive']);

    $active = IntegrationConnector::forTenant(30)->active()->get();
    expect($active)->toHaveCount(1)
        ->and($active->first()->status)->toBe('active');
});

// ---------------------------------------------------------------------------
// Test 16 — IntegrationConnector forTenant scope
// ---------------------------------------------------------------------------

test('IntegrationConnector forTenant scope isolates by tenant_id', function () {
    makeConnector(['tenant_id' => 40, 'slug' => 't40-c']);
    makeConnector(['tenant_id' => 41, 'slug' => 't41-c']);

    $t40 = IntegrationConnector::forTenant(40)->get();
    expect($t40)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Test 17 — IntegrationConnector byProvider scope
// ---------------------------------------------------------------------------

test('IntegrationConnector byProvider scope filters provider_type', function () {
    makeConnector(['tenant_id' => 50, 'slug' => 'w1', 'provider_type' => 'webhook']);
    makeConnector(['tenant_id' => 50, 'slug' => 'k1', 'provider_type' => 'api_key']);

    $webhooks = IntegrationConnector::forTenant(50)->byProvider('webhook')->get();
    expect($webhooks)->toHaveCount(1)
        ->and($webhooks->first()->provider_type)->toBe('webhook');
});

// ---------------------------------------------------------------------------
// Test 18 — SyncLog scopes
// ---------------------------------------------------------------------------

test('SyncLog successful and failed scopes work correctly', function () {
    $connector = makeConnector(['tenant_id' => 60, 'slug' => 'log-test']);

    SyncLog::create([
        'connector_id' => $connector->id, 'tenant_id' => 60,
        'direction' => 'outbound', 'status' => 'success',
        'records_processed' => 5, 'started_at' => now(),
    ]);
    SyncLog::create([
        'connector_id' => $connector->id, 'tenant_id' => 60,
        'direction' => 'inbound', 'status' => 'failed',
        'records_processed' => 0, 'started_at' => now(),
    ]);

    expect(SyncLog::successful()->count())->toBe(1)
        ->and(SyncLog::failed()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Test 19 — WebhookEndpoint belongsTo connector relationship
// ---------------------------------------------------------------------------

test('WebhookEndpoint belongs to IntegrationConnector', function () {
    $connector = makeConnector();

    $endpoint = WebhookEndpoint::create([
        'connector_id'    => $connector->id,
        'url'             => 'https://example.com/hook',
        'method'          => 'POST',
        'is_active'       => true,
        'retry_attempts'  => 3,
        'timeout_seconds' => 30,
    ]);

    expect($endpoint->connector)->toBeInstanceOf(IntegrationConnector::class)
        ->and($endpoint->connector->id)->toBe($connector->id);
});

// ---------------------------------------------------------------------------
// Test 20 — SyncLog stores error_details as JSON array
// ---------------------------------------------------------------------------

test('SyncLog stores and retrieves error_details as array', function () {
    $connector = makeConnector();

    $log = SyncLog::create([
        'connector_id'   => $connector->id,
        'tenant_id'      => 1,
        'direction'      => 'outbound',
        'status'         => 'failed',
        'records_processed' => 0,
        'error_details'  => [['code' => 'HTTP_500', 'url' => 'https://fail.example.com']],
        'started_at'     => now(),
    ]);

    $retrieved = SyncLog::find($log->id);
    expect($retrieved->error_details)->toBeArray()
        ->and($retrieved->error_details[0]['code'])->toBe('HTTP_500');
});

// ---------------------------------------------------------------------------
// Test 21 — IntegrationConnector soft-delete
// ---------------------------------------------------------------------------

test('IntegrationConnector supports soft delete', function () {
    $connector = makeConnector(['slug' => 'soft-delete-test']);
    $id = $connector->id;

    $connector->delete();

    expect(IntegrationConnector::find($id))->toBeNull();
    expect(IntegrationConnector::withTrashed()->find($id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 22 — dispatchWebhook records payload_size in log
// ---------------------------------------------------------------------------

test('dispatchWebhook records payload_size in SyncLog', function () {
    Http::fake([
        'https://size.example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $service = app(IntegrationService::class);
    $connector = makeConnector(['status' => 'active', 'slug' => 'size-test']);

    WebhookEndpoint::create([
        'connector_id'    => $connector->id,
        'url'             => 'https://size.example.com/hook',
        'method'          => 'POST',
        'is_active'       => true,
        'retry_attempts'  => 1,
        'timeout_seconds' => 5,
    ]);

    $payload = ['event' => 'user.signup', 'user_id' => 99, 'email' => 'test@example.com'];
    $log = $service->dispatchWebhook($connector, $payload);

    expect($log->payload_size)->toBeGreaterThan(0)
        ->and($log->payload_size)->toBe(strlen(json_encode($payload)));
});
