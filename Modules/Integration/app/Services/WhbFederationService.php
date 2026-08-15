<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Integration\Models\WhbConnection;

/**
 * Handles cross-server WHB federation:
 *   - Instance discovery (.well-known/widehalo)
 *   - HMAC-SHA256 request signing and verification
 *   - Replay protection (5-minute timestamp window)
 *   - Outbound invite / exchange push
 *   - Session token refresh
 */
class WhbFederationService
{
    private const HTTP_TIMEOUT    = 10;
    private const REPLAY_WINDOW   = 300; // seconds

    // ---------------------------------------------------------------------------
    // Discovery
    // ---------------------------------------------------------------------------

    /**
     * Discover a remote WideHalo instance.
     *
     * GET https://{serverUrl}/.well-known/widehalo
     *
     * @return array{ name: string, version: string, federation_endpoint: string, public_key: string|null }
     */
    public function discover(string $serverUrl): array
    {
        $serverUrl = rtrim($serverUrl, '/');

        $response = Http::timeout(self::HTTP_TIMEOUT)
            ->get("{$serverUrl}/.well-known/widehalo");

        $response->throw();

        return $response->json();
    }

    // ---------------------------------------------------------------------------
    // Signing / verification
    // ---------------------------------------------------------------------------

    /**
     * Generate an HMAC-SHA256 signature for a request body.
     *
     * The signed string is:  {unix_timestamp}.{body}
     *
     * @param  string $body      Raw request body (JSON string)
     * @param  string $secret    Shared secret (hex)
     * @param  int|null $timestamp Unix timestamp (defaults to now)
     * @return string             Hex HMAC digest
     */
    public function sign(string $body, string $secret, ?int $timestamp = null): string
    {
        $ts = $timestamp ?? time();

        return hash_hmac('sha256', $ts . '.' . $body, $secret);
    }

    /**
     * Verify an incoming HMAC-SHA256 signature.
     *
     * @param  string $body      Raw request body
     * @param  string $signature Incoming hex HMAC from X-WH-Signature header
     * @param  string $secret    Shared secret
     * @param  int    $timestamp Unix timestamp from X-WH-Timestamp header
     */
    public function verify(string $body, string $signature, string $secret, int $timestamp): bool
    {
        // Replay protection
        if (abs(time() - $timestamp) > self::REPLAY_WINDOW) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Generate a new shared secret: 32-byte random hex string.
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    // ---------------------------------------------------------------------------
    // Outbound requests
    // ---------------------------------------------------------------------------

    /**
     * Send an invite to a remote WideHalo server.
     *
     * POST {remote}/api/v1/federation/invite
     */
    public function sendInvite(WhbConnection $connection): bool
    {
        if (! $connection->remote_server_url) {
            return false;
        }

        $endpoint = rtrim($connection->remote_server_url, '/') . '/api/v1/federation/invite';

        $secret = $this->resolveSecret($connection);

        // The invite call is the one federation request made before the two
        // servers share anything at all — it is what PROVISIONS the secret
        // on the receiving side, so it has to travel in the payload itself.
        // HTTPS (already enforced on remote_server_url by
        // WhbPartnerService::createInvite()) is what protects it in
        // transit; nothing after this point on either side signs with an
        // unprovisioned secret again.
        $payload = json_encode([
            'invite_code'        => $connection->invite_code,
            'local_tenant_name'  => config('app.name'),
            'initiator_server'   => config('app.url'),
            'expires_at'         => $connection->invite_expires_at?->toIso8601String(),
            'shared_secret'      => $secret,
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = $this->sign($payload, $secret, $timestamp);

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders($this->buildFederationHeaders($signature, $timestamp))
                ->withBody($payload, 'application/json')
                ->post($endpoint);

            $response->throw();

            return true;
        } catch (\Throwable $e) {
            Log::error('[WHB] sendInvite failed', [
                'connection_id' => $connection->id,
                'error'         => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Notify the inviting server that we accepted their invite.
     *
     * POST {remote}/api/v1/federation/accept
     *
     * Symmetric counterpart of receiveAccept() on the inviter's controller:
     * lets the inviter's own WhbConnection row (created not knowing who
     * would accept) learn who we are, the same way acceptInvite() already
     * does for a same-server (local) connection.
     */
    public function sendAccept(WhbConnection $connection, string $localTenantId): bool
    {
        if (! $connection->remote_server_url) {
            return false;
        }

        $endpoint = rtrim($connection->remote_server_url, '/') . '/api/v1/federation/accept';

        $payload = json_encode([
            'invite_code'        => $connection->invite_code,
            'remote_tenant_id'   => $localTenantId,
            'remote_tenant_name' => config('app.name'),
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $secret    = $this->resolveSecret($connection);
        $signature = $this->sign($payload, $secret, $timestamp);

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders($this->buildFederationHeaders($signature, $timestamp))
                ->withBody($payload, 'application/json')
                ->post($endpoint);

            $response->throw();

            return true;
        } catch (\Throwable $e) {
            Log::warning('[WHB] sendAccept failed', [
                'connection_id' => $connection->id,
                'error'         => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Push data to a remote WideHalo server.
     *
     * POST {remote}/api/v1/federation/exchange
     *
     * @param  array<string, mixed> $payload Serialized WHB payload
     */
    public function push(WhbConnection $connection, string $dataType, array $payload): bool
    {
        if (! $connection->remote_server_url) {
            return false;
        }

        $endpoint = rtrim($connection->remote_server_url, '/') . '/api/v1/federation/exchange';

        $body = json_encode([
            'data_type' => $dataType,
            'payload'   => $payload,
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $secret    = $this->resolveSecret($connection);
        $signature = $this->sign($body, $secret, $timestamp);

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders($this->buildFederationHeaders($signature, $timestamp))
                ->withBody($body, 'application/json')
                ->post($endpoint);

            $response->throw();

            return true;
        } catch (\Throwable $e) {
            Log::error('[WHB] push failed', [
                'connection_id' => $connection->id,
                'data_type'     => $dataType,
                'error'         => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Refresh the session token with a remote server.
     *
     * POST {remote}/api/v1/federation/refresh
     */
    public function refreshSession(WhbConnection $connection): void
    {
        if (! $connection->remote_server_url) {
            return;
        }

        $endpoint = rtrim($connection->remote_server_url, '/') . '/api/v1/federation/refresh';

        $newToken  = bin2hex(random_bytes(32));
        $timestamp = time();
        $secret    = $this->resolveSecret($connection);

        $body = json_encode(['new_token' => $newToken], JSON_THROW_ON_ERROR);
        $signature = $this->sign($body, $secret, $timestamp);

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders($this->buildFederationHeaders($signature, $timestamp))
                ->withBody($body, 'application/json')
                ->post($endpoint);

            $response->throw();

            $connection->update([
                'session_token'      => encrypt($newToken),
                'session_expires_at' => now()->addHours(24),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[WHB] refreshSession failed', [
                'connection_id' => $connection->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Build standard federation HTTP headers.
     *
     * @return array<string, string>
     */
    private function buildFederationHeaders(string $signature, int $timestamp): array
    {
        return [
            'X-WH-Instance'  => config('app.url'),
            'X-WH-Signature' => $signature,
            'X-WH-Timestamp' => (string) $timestamp,
            'Accept'         => 'application/json',
        ];
    }

    /**
     * Decrypt and return the shared secret for a connection.
     *
     * Public: also used by VerifyFederationSignature middleware, which
     * needs the exact same decrypt-with-plaintext-fallback logic to verify
     * inbound signatures against.
     */
    public function resolveSecret(WhbConnection $connection): string
    {
        /** @var string $raw */
        $raw = $connection->getRawOriginal('shared_secret')
            ?? $connection->getAttributes()['shared_secret']
            ?? '';

        try {
            return decrypt($raw);
        } catch (\Throwable) {
            return $raw; // already plain (e.g. during initial invite)
        }
    }
}
