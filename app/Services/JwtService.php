<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class JwtService
{
    private const ALGORITHM = 'HS256';

    /**
     * Generate JWT token for user
     */
    public function generateToken(int $userId, array $claims = [], ?int $expiresInSeconds = null): string
    {
        $secret = Config::get('app.jwt_secret') ?? Config::get('app.key');
        $expiresInSeconds = $expiresInSeconds ?? (7 * 24 * 60 * 60); // 7 days default

        $header = $this->base64UrlEncode(json_encode([
            'alg' => self::ALGORITHM,
            'typ' => 'JWT',
        ]));

        $payload = array_merge([
            'iss' => Config::get('app.url'),
            'sub' => $userId,
            'aud' => Config::get('app.name'),
            'iat' => now()->timestamp,
            'exp' => now()->addSeconds($expiresInSeconds)->timestamp,
            'nbf' => now()->timestamp,
            'jti' => Str::uuid(),
        ], $claims);

        $payload = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign($header . '.' . $payload, $secret);

        return $header . '.' . $payload . '.' . $signature;
    }

    /**
     * Verify and decode JWT token
     */
    public function verifyToken(string $token): ?array
    {
        $secret = Config::get('app.jwt_secret') ?? Config::get('app.key');

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->sign($header . '.' . $payload, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode and verify payload
        $decoded = json_decode($this->base64UrlDecode($payload), true);

        if (!is_array($decoded)) {
            return null;
        }

        // Verify expiration
        if (($decoded['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        // Verify not-before time
        if (($decoded['nbf'] ?? 0) > now()->timestamp) {
            return null;
        }

        return $decoded;
    }

    /**
     * Generate refresh token (short-lived)
     */
    public function generateRefreshToken(int $userId, array $claims = []): string
    {
        return $this->generateToken($userId, array_merge($claims, ['type' => 'refresh']), 30 * 24 * 60 * 60); // 30 days
    }

    /**
     * Check if token is refresh token
     */
    public function isRefreshToken(array $payload): bool
    {
        return ($payload['type'] ?? null) === 'refresh';
    }

    /**
     * Sign JWT signature
     */
    private function sign(string $message, string $secret): string
    {
        $hash = hash_hmac('sha256', $message, $secret, true);
        return $this->base64UrlEncode($hash);
    }

    /**
     * Base64 URL encode (JWT compatible)
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode (JWT compatible)
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
