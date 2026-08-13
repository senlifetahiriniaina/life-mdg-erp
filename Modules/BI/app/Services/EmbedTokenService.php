<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\EmbedToken;

/**
 * EmbedTokenService — generate and validate signed JWT embed tokens for BI dashboards.
 *
 * Tokens are HS256-signed JWTs using a per-tenant secret derived from the app key + tenant_id.
 * They grant read-only access to a single dashboard and are scoped to specific allowed domains.
 */
class EmbedTokenService
{
    /**
     * Generate a signed embed token for a dashboard.
     *
     * @param  int  $dashboardId
     * @param  int  $tenantId
     * @param  list<string>  $allowedDomains
     * @param  int  $expiresIn  Seconds until expiry (default 1 h)
     * @param  int  $createdBy  User ID creating the token
     * @return array{token: string, expires_at: string, embed_token_id: int}
     */
    public function generateEmbedToken(
        int $dashboardId,
        int $tenantId,
        array $allowedDomains,
        int $expiresIn = 3600,
        int $createdBy = 0
    ): array {
        $expiresAt = Carbon::now()->addSeconds($expiresIn);
        $jti = Str::uuid()->toString();

        $header  = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64url(json_encode([
            'jti'          => $jti,
            'dashboard_id' => $dashboardId,
            'tenant_id'    => $tenantId,
            'scope'        => 'embed:read',
            'domains'      => $allowedDomains,
            'exp'          => $expiresAt->getTimestamp(),
            'iat'          => Carbon::now()->getTimestamp(),
        ]));

        $secret    = $this->tenantSecret($tenantId);
        $signature = $this->base64url(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));
        $token     = "{$header}.{$payload}.{$signature}";

        // Persist record for revocation / audit
        $record = EmbedToken::create([
            'dashboard_id'    => $dashboardId,
            'tenant_id'       => $tenantId,
            'token_hash'      => Hash::make($token),
            'jti'             => $jti,
            'allowed_domains' => $allowedDomains,
            'expires_at'      => $expiresAt,
            'created_by'      => $createdBy,
        ]);

        return [
            'token'          => $token,
            'expires_at'     => $expiresAt->toIso8601String(),
            'embed_token_id' => $record->id,
        ];
    }

    /**
     * Validate an embed token and return its decoded payload.
     *
     * @return array{dashboard_id: int, tenant_id: int, domains: list<string>, exp: int}
     *
     * @throws \RuntimeException if token is invalid, expired, or revoked
     */
    public function validateEmbedToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid token structure.');
        }

        [$header, $payload, $signature] = $parts;

        $decoded = json_decode($this->base64urlDecode($payload), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Invalid token payload.');
        }

        // Verify expiry before signature check to avoid timing attacks on expired tokens
        if (($decoded['exp'] ?? 0) < Carbon::now()->getTimestamp()) {
            throw new \RuntimeException('Embed token has expired.');
        }

        $tenantId = (int) ($decoded['tenant_id'] ?? 0);
        if ($tenantId === 0) {
            throw new \RuntimeException('Token missing tenant_id claim.');
        }

        // Signature verification
        $expected = $this->base64url(
            hash_hmac('sha256', "{$header}.{$payload}", $this->tenantSecret($tenantId), true)
        );
        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid token signature.');
        }

        // Revocation check — look up record by jti (cached 60 s to avoid DB hammering)
        $jti    = $decoded['jti'] ?? '';
        $exists = Cache::remember("embed_token_jti:{$jti}", 60, function () use ($jti) {
            return EmbedToken::where('jti', $jti)->whereNull('revoked_at')->exists();
        });
        if (! $exists) {
            throw new \RuntimeException('Token has been revoked or does not exist.');
        }

        return $decoded;
    }

    /**
     * Validate that the request origin is in the token's allowed_domains list.
     *
     * @param  list<string>  $allowedDomains
     */
    public function isAllowedOrigin(string $origin, array $allowedDomains): bool
    {
        if (empty($allowedDomains)) {
            return false;
        }
        $host = parse_url($origin, PHP_URL_HOST) ?? $origin;
        foreach ($allowedDomains as $domain) {
            $domain = ltrim($domain, '*.');
            if ($host === $domain || str_ends_with($host, ".{$domain}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Revoke an embed token by jti.
     */
    public function revokeToken(string $jti): bool
    {
        Cache::forget("embed_token_jti:{$jti}");

        return (bool) EmbedToken::where('jti', $jti)->update(['revoked_at' => Carbon::now()]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function tenantSecret(int $tenantId): string
    {
        return hash_hmac('sha256', "embed_tenant_{$tenantId}", config('app.key'));
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        $pad  = strlen($data) % 4;
        $data = $pad ? $data . str_repeat('=', 4 - $pad) : $data;

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
