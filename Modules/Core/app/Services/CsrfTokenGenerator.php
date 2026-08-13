<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Str;

/**
 * CSRF Token Generator
 *
 * Generates cryptographically secure CSRF tokens with support for multiple formats
 * and token versioning for rotation tracking.
 *
 * Token Format:
 * - 32+ bytes of random data (configurable)
 * - Multiple output formats: hex, base64, base64url
 * - Version prefix for rotation tracking
 */
class CsrfTokenGenerator
{
    /**
     * Default token length in bytes (32 bytes = 256 bits)
     */
    private int $tokenLength;

    /**
     * Current token version for rotation tracking
     */
    private int $tokenVersion = 1;

    /**
     * Constructor
     */
    public function __construct(int $tokenLength = 32)
    {
        $this->tokenLength = max(32, $tokenLength);
    }

    /**
     * Generate a new CSRF token
     *
     * Returns a URL-safe token suitable for use in headers and form fields.
     *
     * @return string Base64url-encoded token
     */
    public function generate(): string
    {
        // Generate cryptographically secure random bytes
        $randomBytes = random_bytes($this->tokenLength);

        // Return base64url-encoded token (URL-safe, no padding)
        return $this->toBase64Url($randomBytes);
    }

    /**
     * Generate a token with version prefix for rotation tracking
     *
     * Format: v{version}.{token}
     *
     * @return string Versioned token
     */
    public function generateVersioned(): string
    {
        $token = $this->generate();
        return "v{$this->tokenVersion}.{$token}";
    }

    /**
     * Generate a hex-encoded token (for debugging/logging)
     *
     * @return string Hex-encoded token
     */
    public function generateHex(): string
    {
        return bin2hex(random_bytes($this->tokenLength));
    }

    /**
     * Generate a token with metadata binding
     *
     * Creates a token that includes encrypted metadata to prevent token fixation.
     *
     * @param array $metadata Metadata to bind (ip, user_agent, etc)
     * @return string Metadata-bound token
     */
    public function generateWithMetadata(array $metadata): string
    {
        $token = $this->generate();
        $metadataHash = hash('sha256', json_encode($metadata));

        // Combine token with metadata hash
        return "{$token}.{$metadataHash}";
    }

    /**
     * Hash a token for database storage
     *
     * Uses SHA-256 for secure token hashing. Never store plain tokens in database.
     *
     * @param string $token The plain token
     * @return string Hashed token
     */
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Verify a token against a hash
     *
     * Uses timing-safe comparison to prevent timing attacks.
     *
     * @param string $token The plain token
     * @param string $hash The stored hash
     * @return bool True if token matches hash
     */
    public function verify(string $token, string $hash): bool
    {
        $tokenHash = $this->hash($token);
        return hash_equals($tokenHash, $hash);
    }

    /**
     * Extract token from versioned string
     *
     * @param string $versionedToken The versioned token (v1.token)
     * @return array ['version' => int, 'token' => string] or null if invalid format
     */
    public function parseVersioned(string $versionedToken): ?array
    {
        if (!str_contains($versionedToken, '.')) {
            return null;
        }

        $parts = explode('.', $versionedToken, 2);

        if (count($parts) !== 2) {
            return null;
        }

        $version = str_replace('v', '', $parts[0]);

        if (!is_numeric($version)) {
            return null;
        }

        return [
            'version' => (int)$version,
            'token' => $parts[1],
        ];
    }

    /**
     * Convert binary data to base64url encoding (URL-safe, no padding)
     *
     * @param string $data Binary data
     * @return string Base64url-encoded string
     */
    private function toBase64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Convert base64url string to binary data
     *
     * @param string $data Base64url-encoded string
     * @return string Binary data
     */
    private function fromBase64Url(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', strlen($data) % 4), true);
    }

    /**
     * Set token length
     *
     * @param int $length Token length in bytes (minimum 32)
     */
    public function setTokenLength(int $length): self
    {
        $this->tokenLength = max(32, $length);
        return $this;
    }

    /**
     * Set token version
     *
     * @param int $version Token version number
     */
    public function setTokenVersion(int $version): self
    {
        $this->tokenVersion = max(1, $version);
        return $this;
    }

    /**
     * Get current token length
     */
    public function getTokenLength(): int
    {
        return $this->tokenLength;
    }

    /**
     * Get current token version
     */
    public function getTokenVersion(): int
    {
        return $this->tokenVersion;
    }

    /**
     * Hash metadata for token binding
     *
     * @param string $ipAddress IP address
     * @param string $userAgent User agent string
     * @return string Metadata hash
     */
    public function hashMetadata(string $ipAddress, string $userAgent): string
    {
        $metadata = [
            'ip' => $ipAddress,
            'ua' => $userAgent,
        ];

        return hash('sha256', json_encode($metadata));
    }

    /**
     * Generate a random string for use as scope/action identifier
     *
     * @param int $length Length of random string
     * @return string Random string
     */
    public static function randomIdentifier(int $length = 16): string
    {
        return Str::random($length);
    }
}
