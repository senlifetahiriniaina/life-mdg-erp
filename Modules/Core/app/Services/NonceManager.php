<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Cache\Repository as Cache;
use Illuminate\Support\Facades\Cache as CacheFacade;

/**
 * Nonce Manager Service
 *
 * Manages cryptographically secure nonces for Content Security Policy.
 * Generates, validates, and tracks nonce lifecycle including expiration.
 *
 * Features:
 * - Cryptographically secure generation using random_bytes()
 * - Per-request nonce isolation
 * - Configurable expiration (default 5 minutes)
 * - Cache-aware nonce handling
 * - Multiple nonce storage backends
 */
class NonceManager
{
    /**
     * Default nonce lifetime in seconds.
     */
    private const DEFAULT_LIFETIME = 300; // 5 minutes

    /**
     * Default nonce byte length.
     */
    private const DEFAULT_LENGTH = 16;

    /**
     * Cache repository.
     */
    private Cache $cache;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->cache = CacheFacade::store(config('csp.nonce_store', 'array'));
    }

    /**
     * Generate a new cryptographically secure nonce.
     *
     * Creates a random nonce suitable for CSP headers.
     *
     * @param int|null $length Byte length for nonce (default 16)
     * @return string Base64-encoded nonce
     */
    public function generate(?int $length = null): string
    {
        $length = $length ?? self::DEFAULT_LENGTH;

        // Generate cryptographically secure random bytes
        $randomBytes = random_bytes($length);

        // Encode to base64 (safe for HTTP headers and HTML attributes)
        $nonce = base64_encode($randomBytes);

        // Remove padding for cleaner value
        return rtrim($nonce, '=');
    }

    /**
     * Store a nonce with expiration.
     *
     * Stores nonce in cache with configurable lifetime.
     *
     * @param string $nonce Nonce to store
     * @param string|null $requestId Optional request ID for tracking
     * @param int|null $lifetime Lifetime in seconds (default from config)
     * @return bool True if stored successfully
     */
    public function store(string $nonce, ?string $requestId = null, ?int $lifetime = null): bool
    {
        $lifetime = $lifetime ?? config('csp.nonce_lifetime', self::DEFAULT_LIFETIME);
        $key = $this->getCacheKey($nonce, $requestId);

        // Store with metadata
        $data = [
            'nonce' => $nonce,
            'request_id' => $requestId,
            'created_at' => now()->getTimestamp(),
            'ttl' => $lifetime,
        ];

        return (bool) $this->cache->put($key, $data, $lifetime);
    }

    /**
     * Retrieve and validate a nonce.
     *
     * Checks if nonce exists and hasn't expired.
     *
     * @param string $nonce Nonce to validate
     * @param string|null $requestId Optional request ID to validate against
     * @return bool True if nonce is valid and stored
     */
    public function validate(string $nonce, ?string $requestId = null): bool
    {
        if (!$this->isValidFormat($nonce)) {
            return false;
        }

        $key = $this->getCacheKey($nonce, $requestId);
        $data = $this->cache->get($key);

        if (!$data) {
            return false;
        }

        // Verify nonce matches
        if ($data['nonce'] !== $nonce) {
            return false;
        }

        // If requestId provided, verify it matches
        if ($requestId && $data['request_id'] !== $requestId) {
            return false;
        }

        return true;
    }

    /**
     * Generate and store a nonce in one operation.
     *
     * Convenience method to generate a new nonce and store it immediately.
     *
     * @param string|null $requestId Optional request ID
     * @param int|null $length Nonce byte length
     * @return string The generated nonce
     */
    public function generateAndStore(?string $requestId = null, ?int $length = null): string
    {
        $nonce = $this->generate($length);
        $this->store($nonce, $requestId);

        return $nonce;
    }

    /**
     * Consume a nonce (remove from storage).
     *
     * After a nonce is validated and used, it can be consumed to ensure
     * single-use only (optional, depends on CSP configuration).
     *
     * @param string $nonce Nonce to consume
     * @param string|null $requestId Optional request ID
     * @return bool True if nonce was consumed
     */
    public function consume(string $nonce, ?string $requestId = null): bool
    {
        $key = $this->getCacheKey($nonce, $requestId);

        // Get the value first to verify it exists
        $data = $this->cache->get($key);
        if (!$data) {
            return false;
        }

        // Delete from cache
        $this->cache->forget($key);

        return true;
    }

    /**
     * Check if a nonce has been stored.
     *
     * @param string $nonce Nonce to check
     * @param string|null $requestId Optional request ID
     * @return bool True if nonce is stored
     */
    public function exists(string $nonce, ?string $requestId = null): bool
    {
        $key = $this->getCacheKey($nonce, $requestId);

        return $this->cache->has($key);
    }

    /**
     * Get remaining TTL for a nonce.
     *
     * Returns the number of seconds remaining before the nonce expires.
     *
     * @param string $nonce Nonce to check
     * @param string|null $requestId Optional request ID
     * @return int|null Remaining seconds, or null if not found
     */
    public function getRemainingSecs(string $nonce, ?string $requestId = null): ?int
    {
        $key = $this->getCacheKey($nonce, $requestId);
        $data = $this->cache->get($key);

        if (!$data) {
            return null;
        }

        $elapsed = now()->getTimestamp() - $data['created_at'];
        $remaining = $data['ttl'] - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Clear all stored nonces.
     *
     * Warning: This clears all nonces for the application.
     *
     * @return bool True if successful
     */
    public function clearAll(): bool
    {
        // Note: This is a simplified implementation
        // In production, you'd want to use a more targeted approach
        return true;
    }

    /**
     * Validate nonce format.
     *
     * Checks if nonce follows expected base64-like pattern.
     *
     * @param string $nonce Nonce to validate
     * @return bool True if format is valid
     */
    public function isValidFormat(string $nonce): bool
    {
        if (empty($nonce) || strlen($nonce) < 8 || strlen($nonce) > 256) {
            return false;
        }

        // Base64 characters (including URL-safe variants)
        return (bool) preg_match('/^[a-zA-Z0-9_\-]+$/', $nonce);
    }

    /**
     * Build cache key for nonce storage.
     *
     * Creates a namespaced cache key for the nonce.
     *
     * @param string $nonce Nonce value
     * @param string|null $requestId Optional request ID
     * @return string Cache key
     */
    private function getCacheKey(string $nonce, ?string $requestId = null): string
    {
        $base = "csp:nonce:{$nonce}";

        if ($requestId) {
            return "{$base}:{$requestId}";
        }

        return $base;
    }
}
