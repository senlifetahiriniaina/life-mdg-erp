<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integration\Models\WhbConnection;
use Modules\Integration\Models\WhbExchange;
use Modules\Integration\Models\WhbPermission;
use Modules\Integration\Services\WhbFederationService;

/**
 * Covers the 4 previously-missing WhbFederationController receiver methods
 * (receiveInvite/receiveAccept/receiveExchange/receiveRefresh) and the
 * VerifyFederationSignature middleware guarding 3 of them. Before this,
 * /api/v1/federation/* 500'd on every request — the route file referenced
 * a controller method and a middleware class that didn't exist.
 */
uses(RefreshDatabase::class);

function federationService(): WhbFederationService
{
    return app(WhbFederationService::class);
}

function signedHeaders(string $body, string $secret, ?int $timestamp = null): array
{
    $timestamp ??= time();

    return [
        'X-WH-Instance'  => 'https://partner.example.test',
        'X-WH-Signature' => federationService()->sign($body, $secret, $timestamp),
        'X-WH-Timestamp' => (string) $timestamp,
    ];
}

function makeEstablishedConnection(array $overrides = []): array
{
    $secret = federationService()->generateSecret();

    $connection = WhbConnection::create(array_merge([
        'local_tenant_id'    => 'default',
        'remote_server_url'  => 'https://partner.example.test',
        'remote_tenant_name' => 'Partner Co',
        'connection_type'    => 'remote',
        'status'             => 'active',
        'invite_code'        => strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        'shared_secret'      => encrypt($secret),
    ], $overrides));

    return [$connection, $secret];
}

test('receiveInvite creates a pending connection without a signature', function () {
    $payload = [
        'invite_code'       => 'ABCD2345',
        'local_tenant_name' => 'Remote Co',
        'initiator_server'  => 'https://remote.example.test',
        'expires_at'        => now()->addHours(24)->toIso8601String(),
        'shared_secret'     => bin2hex(random_bytes(32)),
    ];

    $response = $this->postJson('/api/v1/federation/invite', $payload);

    $response->assertCreated();
    $response->assertJsonPath('status', 'received');

    $connection = WhbConnection::where('invite_code', 'ABCD2345')->first();
    expect($connection)->not->toBeNull();
    expect($connection->status)->toBe('pending');
    expect($connection->remote_server_url)->toBe('https://remote.example.test');
    expect($connection->remote_tenant_name)->toBe('Remote Co');
    expect(decrypt($connection->getRawOriginal('shared_secret')))->toBe($payload['shared_secret']);
});

test('receiveInvite rejects a non-https initiator_server', function () {
    $response = $this->postJson('/api/v1/federation/invite', [
        'invite_code'       => 'ABCD2346',
        'local_tenant_name' => 'Remote Co',
        'initiator_server'  => 'http://remote.example.test',
        'shared_secret'     => bin2hex(random_bytes(32)),
    ]);

    $response->assertStatus(422);
    expect(WhbConnection::where('invite_code', 'ABCD2346')->exists())->toBeFalse();
});

test('receiveInvite rejects a duplicate invite_code', function () {
    [$connection] = makeEstablishedConnection(['invite_code' => 'DUPE2345']);

    $response = $this->postJson('/api/v1/federation/invite', [
        'invite_code'       => 'DUPE2345',
        'local_tenant_name' => 'Someone Else',
        'initiator_server'  => 'https://other.example.test',
        'shared_secret'     => bin2hex(random_bytes(32)),
    ]);

    $response->assertStatus(409);
});

test('federation routes reject requests with no signature headers', function () {
    $response = $this->postJson('/api/v1/federation/exchange', [
        'data_type' => 'invoice',
        'payload'   => [],
    ]);

    $response->assertStatus(401);
});

test('federation routes reject an invalid signature', function () {
    [$connection, $secret] = makeEstablishedConnection();

    $body = json_encode(['data_type' => 'invoice', 'payload' => ['x' => 1]]);
    $headers = signedHeaders($body, $secret);
    $headers['X-WH-Signature'] = 'not-the-real-signature';

    $response = $this->call('POST', '/api/v1/federation/exchange', [], [], [], [
        'HTTP_X-WH-Instance'  => $headers['X-WH-Instance'],
        'HTTP_X-WH-Signature' => $headers['X-WH-Signature'],
        'HTTP_X-WH-Timestamp' => $headers['X-WH-Timestamp'],
        'CONTENT_TYPE'        => 'application/json',
    ], $body);

    $response->assertStatus(401);
});

test('federation routes reject a replayed (stale) timestamp', function () {
    [$connection, $secret] = makeEstablishedConnection();

    $body = json_encode(['data_type' => 'invoice', 'payload' => ['x' => 1]]);
    $staleTimestamp = time() - 600; // outside the 300s replay window
    $headers = signedHeaders($body, $secret, $staleTimestamp);

    $response = $this->call('POST', '/api/v1/federation/exchange', [], [], [], [
        'HTTP_X-WH-Instance'  => $headers['X-WH-Instance'],
        'HTTP_X-WH-Signature' => $headers['X-WH-Signature'],
        'HTTP_X-WH-Timestamp' => $headers['X-WH-Timestamp'],
        'CONTENT_TYPE'        => 'application/json',
    ], $body);

    $response->assertStatus(401);
});

test('receiveAccept fills in remote tenant info when the signature matches the invite_code', function () {
    [$connection, $secret] = makeEstablishedConnection([
        'status'           => 'pending',
        'remote_tenant_id' => null,
    ]);

    $body = json_encode([
        'invite_code'        => $connection->invite_code,
        'remote_tenant_id'   => 'partner-tenant',
        'remote_tenant_name' => 'Partner Renamed',
    ]);
    $headers = signedHeaders($body, $secret);

    $response = $this->call('POST', '/api/v1/federation/accept', [], [], [], [
        'HTTP_X-WH-Instance'  => $headers['X-WH-Instance'],
        'HTTP_X-WH-Signature' => $headers['X-WH-Signature'],
        'HTTP_X-WH-Timestamp' => $headers['X-WH-Timestamp'],
        'CONTENT_TYPE'        => 'application/json',
    ], $body);

    $response->assertOk();
    $connection->refresh();
    expect($connection->remote_tenant_id)->toBe('partner-tenant');
    expect($connection->remote_tenant_name)->toBe('Partner Renamed');
});

test('receiveAccept rejects an invite_code that does not belong to the signed connection', function () {
    [$connectionA, $secretA] = makeEstablishedConnection();
    [$connectionB] = makeEstablishedConnection(['remote_server_url' => 'https://partner-b.example.test']);

    // Signed correctly for connection A (X-WH-Instance matches A), but the
    // body claims connection B's invite_code.
    $body = json_encode([
        'invite_code'      => $connectionB->invite_code,
        'remote_tenant_id' => 'attacker-tenant',
    ]);
    $headers = signedHeaders($body, $secretA);

    $response = $this->call('POST', '/api/v1/federation/accept', [], [], [], [
        'HTTP_X-WH-Instance'  => $headers['X-WH-Instance'],
        'HTTP_X-WH-Signature' => $headers['X-WH-Signature'],
        'HTTP_X-WH-Timestamp' => $headers['X-WH-Timestamp'],
        'CONTENT_TYPE'        => 'application/json',
    ], $body);

    $response->assertStatus(401);
    expect($connectionB->fresh()->remote_tenant_id)->not->toBe('attacker-tenant');
});

test('receiveExchange stores an inbound exchange when the partner is permitted to send that data type', function () {
    [$connection, $secret] = makeEstablishedConnection();

    WhbPermission::create([
        'connection_id' => $connection->id,
        'data_type'     => 'invoice',
        'can_receive'   => true,
        'can_send'      => false,
        'auto_accept'   => true,
    ]);

    $body = json_encode(['data_type' => 'invoice', 'payload' => ['number' => 'INV-1']]);
    $headers = signedHeaders($body, $secret);

    $response = $this->call('POST', '/api/v1/federation/exchange', [], [], [], [
        'HTTP_X-WH-Instance'  => $headers['X-WH-Instance'],
        'HTTP_X-WH-Signature' => $headers['X-WH-Signature'],
        'HTTP_X-WH-Timestamp' => $headers['X-WH-Timestamp'],
        'CONTENT_TYPE'        => 'application/json',
    ], $body);

    $response->assertCreated();

    $exchange = WhbExchange::where('connection_id', $connection->id)->first();
    expect($exchange)->not->toBeNull();
    expect($exchange->direction)->toBe('inbound');
    expect($exchange->status)->toBe('accepted'); // auto_accept
    expect($connection->fresh()->last_sync_at)->not->toBeNull();
});

test('receiveExchange rejects a data_type the partner has no receive permission for', function () {
    [$connection, $secret] = makeEstablishedConnection();

    $body = json_encode(['data_type' => 'invoice', 'payload' => ['number' => 'INV-1']]);
    $headers = signedHeaders($body, $secret);

    $response = $this->call('POST', '/api/v1/federation/exchange', [], [], [], [
        'HTTP_X-WH-Instance'  => $headers['X-WH-Instance'],
        'HTTP_X-WH-Signature' => $headers['X-WH-Signature'],
        'HTTP_X-WH-Timestamp' => $headers['X-WH-Timestamp'],
        'CONTENT_TYPE'        => 'application/json',
    ], $body);

    $response->assertStatus(403);
    expect(WhbExchange::where('connection_id', $connection->id)->exists())->toBeFalse();
});

test('receiveRefresh stores the new session token', function () {
    [$connection, $secret] = makeEstablishedConnection();

    $body = json_encode(['new_token' => 'brand-new-token']);
    $headers = signedHeaders($body, $secret);

    $response = $this->call('POST', '/api/v1/federation/refresh', [], [], [], [
        'HTTP_X-WH-Instance'  => $headers['X-WH-Instance'],
        'HTTP_X-WH-Signature' => $headers['X-WH-Signature'],
        'HTTP_X-WH-Timestamp' => $headers['X-WH-Timestamp'],
        'CONTENT_TYPE'        => 'application/json',
    ], $body);

    $response->assertOk();

    $connection->refresh();
    expect(decrypt($connection->getRawOriginal('session_token')))->toBe('brand-new-token');
    expect($connection->session_expires_at)->not->toBeNull();
});
