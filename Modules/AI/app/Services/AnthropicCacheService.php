<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Manages caching of Anthropic API responses to reduce costs and improve performance.
 * Uses Redis with TTL-based expiration for scalability.
 */
class AnthropicCacheService
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes
    private const CACHE_PREFIX = 'ai:response:';

    /**
     * Get cached response or null if not found.
     */
    public function get(string $cacheKey): ?array
    {
        $key = $this->buildKey($cacheKey);

        try {
            $cached = Cache::get($key);
            if ($cached !== null) {
                Log::debug('Claude API response cache hit', ['key' => $cacheKey]);
            }
            return $cached;
        } catch (\Exception $e) {
            Log::warning('Cache retrieval failed, continuing without cache', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Store response in cache.
     */
    public function put(string $cacheKey, array $response, ?int $ttl = null): void
    {
        $key = $this->buildKey($cacheKey);
        $ttl ??= self::CACHE_TTL_SECONDS;

        try {
            Cache::put($key, $response, $ttl);
            Log::debug('Claude API response cached', [
                'key' => $cacheKey,
                'ttl' => $ttl,
            ]);
        } catch (\Exception $e) {
            Log::warning('Cache storage failed, continuing without cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate cache key from request parameters.
     * Hash includes: module + action + locale + role + context
     */
    public function generateKey(
        string $module,
        string $action,
        string $locale,
        string $userRole,
        array $context = []
    ): string {
        $data = [
            'module' => $module,
            'action' => $action,
            'locale' => $locale,
            'role' => $userRole,
            'context_hash' => hash('sha256', json_encode($context)),
        ];

        return hash('sha256', json_encode($data));
    }

    /**
     * Clear cache for specific module+action combination.
     */
    public function forget(string $module, string $action): int
    {
        try {
            Cache::tags(['ai', $module, $action])->flush();
            return 1;
        } catch (\Exception $e) {
            Log::warning('Cache flush failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Clear all AI caches.
     */
    public function flush(): bool
    {
        try {
            Cache::tags('ai')->flush();
            return true;
        } catch (\Exception $e) {
            Log::warning('Cache flush all failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        return [
            'store' => config('cache.default'),
            'ttl_seconds' => self::CACHE_TTL_SECONDS,
            'prefix' => self::CACHE_PREFIX,
        ];
    }

    private function buildKey(string $cacheKey): string
    {
        return self::CACHE_PREFIX . $cacheKey;
    }
}
