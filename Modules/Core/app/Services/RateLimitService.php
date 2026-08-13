<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\RateLimitMetrics;

/**
 * RateLimitService
 *
 * Implements sliding window rate limiting algorithm for API endpoints.
 * Supports per-endpoint and per-user-tier rate limits with graceful degradation.
 */
class RateLimitService
{
    private ?Repository $cache = null;
    private ?string $cacheStore = null;
    private ?array $config = null;

    public function __construct()
    {
        // Lazy-load on first use to avoid issues during service resolution
    }

    /**
     * Ensure the cache is initialized.
     */
    private function ensureInitialized(): void
    {
        if ($this->cache === null) {
            $this->config = config('rate_limit', []);
            $this->cacheStore = $this->config['cache_store'] ?? 'redis';
            $this->cache = Cache::store($this->cacheStore);
        }
    }

    /**
     * Check if a request should be rate limited.
     */
    public function checkRateLimit(
        string $identifier,
        string $endpoint,
        string $userTier = 'free',
        ?string $tenantId = null
    ): RateLimitResult {
        try {
            $this->ensureInitialized();

            $limits = $this->getLimitsForEndpoint($endpoint, $userTier);

            if ($limits === null) {
                return RateLimitResult::allow();
            }

            $windowKey = $this->getCacheKey($identifier, $endpoint, 'minute');
            $currentRequests = (int)($this->cache->get($windowKey) ?? 0);

            $allowed = $currentRequests < $limits['requests_per_minute'];

            if ($allowed) {
                $this->recordRequest($identifier, $endpoint, $tenantId);

                $remaining = $limits['requests_per_minute'] - $currentRequests - 1;

                return RateLimitResult::allow(
                    remaining: max(0, $remaining),
                    limit: $limits['requests_per_minute'],
                    resetInSeconds: $this->getResetTimeInSeconds($windowKey),
                );
            }

            $resetInSeconds = $this->getResetTimeInSeconds($windowKey);

            return RateLimitResult::deny(
                remaining: 0,
                limit: $limits['requests_per_minute'],
                resetInSeconds: $resetInSeconds,
            );
        } catch (\Exception $e) {
            \Log::warning('RateLimitService error: ' . $e->getMessage());
            return RateLimitResult::allow();
        }
    }

    /**
     * Get remaining quota for an identifier on an endpoint.
     */
    public function getRemainingQuota(
        string $identifier,
        string $endpoint,
        string $userTier = 'free'
    ): int {
        try {
            $this->ensureInitialized();

            $limits = $this->getLimitsForEndpoint($endpoint, $userTier);

            if ($limits === null) {
                return PHP_INT_MAX;
            }

            $windowKey = $this->getCacheKey($identifier, $endpoint, 'minute');
            $currentRequests = (int)($this->cache->get($windowKey) ?? 0);

            return max(0, $limits['requests_per_minute'] - $currentRequests);
        } catch (\Exception $e) {
            \Log::warning('RateLimitService getRemainingQuota error: ' . $e->getMessage());
            return PHP_INT_MAX;
        }
    }

    /**
     * Check if circuit breaker is open for an endpoint.
     */
    public function isCircuitBreakerOpen(string $endpoint): bool
    {
        try {
            $this->ensureInitialized();

            $key = "circuit_breaker:{$endpoint}";
            return (bool)$this->cache->get($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Open circuit breaker for an endpoint.
     */
    public function openCircuitBreaker(string $endpoint, int $durationSeconds = 300): void
    {
        try {
            $this->ensureInitialized();

            $key = "circuit_breaker:{$endpoint}";
            $this->cache->put($key, true, $durationSeconds);
        } catch (\Exception $e) {
            \Log::warning("Failed to open circuit breaker for {$endpoint}: " . $e->getMessage());
        }
    }

    /**
     * Close circuit breaker for an endpoint.
     */
    public function closeCircuitBreaker(string $endpoint): void
    {
        try {
            $this->ensureInitialized();

            $key = "circuit_breaker:{$endpoint}";
            $this->cache->forget($key);
        } catch (\Exception $e) {
            \Log::warning("Failed to close circuit breaker for {$endpoint}: " . $e->getMessage());
        }
    }

    /**
     * Record a request for metrics and analytics.
     */
    public function recordRequest(
        string $identifier,
        string $endpoint,
        ?string $tenantId = null,
        int $statusCode = 200,
        int $responseTimeMs = 0,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
            $this->ensureInitialized();

            $windowKey = $this->getCacheKey($identifier, $endpoint, 'minute');
            $current = (int)($this->cache->get($windowKey) ?? 0);
            $this->cache->put($windowKey, $current + 1, 61);

            $userId = null;
            if (str_starts_with($identifier, 'user:')) {
                $userId = (int)explode(':', $identifier)[1];
            }

            RateLimitMetrics::create([
                'user_id' => $userId,
                'endpoint' => $endpoint,
                'timestamp' => now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTimeMs,
                'tenant_id' => $tenantId,
            ]);
        } catch (\Exception $e) {
            \Log::debug('RateLimitService recordRequest error: ' . $e->getMessage());
        }
    }

    /**
     * Check if an IP is blocked.
     */
    public function isBlocked(string $ipAddress): bool
    {
        try {
            $this->ensureInitialized();

            $key = "blocked_ip:{$ipAddress}";
            return (bool)$this->cache->get($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Block an IP address.
     */
    public function blockIp(string $ipAddress, int $durationSeconds = 3600): void
    {
        try {
            $this->ensureInitialized();

            $key = "blocked_ip:{$ipAddress}";
            $this->cache->put($key, true, $durationSeconds);
        } catch (\Exception $e) {
            \Log::warning("Failed to block IP {$ipAddress}: " . $e->getMessage());
        }
    }

    /**
     * Unblock an IP address.
     */
    public function unblockIp(string $ipAddress): void
    {
        try {
            $this->ensureInitialized();

            $key = "blocked_ip:{$ipAddress}";
            $this->cache->forget($key);
        } catch (\Exception $e) {
            \Log::warning("Failed to unblock IP {$ipAddress}: " . $e->getMessage());
        }
    }

    /**
     * Get statistics for rate limiting (for response headers).
     */
    public function getStats(string $identifier, string $endpoint, string $userTier = 'free'): array
    {
        try {
            $this->ensureInitialized();

            $limits = $this->getLimitsForEndpoint($endpoint, $userTier);

            if ($limits === null) {
                return [
                    'limit' => PHP_INT_MAX,
                    'remaining' => PHP_INT_MAX,
                    'reset_at' => null,
                    'reset_in_seconds' => 0,
                ];
            }

            $windowKey = $this->getCacheKey($identifier, $endpoint, 'minute');
            $currentRequests = (int)($this->cache->get($windowKey) ?? 0);
            $resetInSeconds = $this->getResetTimeInSeconds($windowKey);

            return [
                'limit' => $limits['requests_per_minute'],
                'current_requests' => $currentRequests,
                'remaining' => max(0, $limits['requests_per_minute'] - $currentRequests),
                'reset_at' => now()->addSeconds($resetInSeconds)->timestamp,
                'reset_in_seconds' => max(0, $resetInSeconds),
                'percentage_used' => round(($currentRequests / $limits['requests_per_minute']) * 100, 2),
            ];
        } catch (\Exception $e) {
            \Log::warning('RateLimitService getStats error: ' . $e->getMessage());
            return [
                'limit' => PHP_INT_MAX,
                'remaining' => PHP_INT_MAX,
                'reset_at' => null,
                'reset_in_seconds' => 0,
                'percentage_used' => 0,
            ];
        }
    }

    /**
     * Get limits configured for an endpoint and user tier.
     */
    private function getLimitsForEndpoint(string $endpoint, string $userTier): ?array
    {
        $endpoints = config('rate_limit.endpoints', []);
        $endpointConfig = $endpoints[$endpoint] ?? null;
        $tierConfig = config("rate_limit.tenant_tiers.{$userTier}");

        if ($endpointConfig && $tierConfig) {
            // When enforce_cap is set on an endpoint, it acts as a hard cap regardless of tier.
            // Otherwise tier can upgrade the limit above the endpoint default.
            $enforceCap = $endpointConfig['enforce_cap'] ?? false;
            $endpointRpm = $endpointConfig['requests_per_minute'] ?? 60;
            $tierRpm     = $tierConfig['requests_per_minute'] ?? 60;

            return [
                'requests_per_minute' => $enforceCap ? $endpointRpm : max($endpointRpm, $tierRpm),
                'requests_per_hour' => $enforceCap
                    ? ($endpointConfig['requests_per_hour'] ?? 3600)
                    : max($endpointConfig['requests_per_hour'] ?? 3600, $tierConfig['requests_per_hour'] ?? 3600),
                'burst_size' => $endpointConfig['burst_size'] ?? 5,
            ];
        }

        if ($endpointConfig) {
            return [
                'requests_per_minute' => $endpointConfig['requests_per_minute'] ?? 60,
                'requests_per_hour' => $endpointConfig['requests_per_hour'] ?? 3600,
                'burst_size' => $endpointConfig['burst_size'] ?? 5,
            ];
        }

        if ($tierConfig) {
            return [
                'requests_per_minute' => $tierConfig['requests_per_minute'] ?? 60,
                'requests_per_hour' => $tierConfig['requests_per_hour'] ?? 3600,
                'burst_size' => 5,
            ];
        }

        return null;
    }

    /**
     * Generate cache key for rate limiting window.
     */
    private function getCacheKey(string $identifier, string $endpoint, string $window = 'minute'): string
    {
        $prefix = config('rate_limit.key_prefix', 'rate_limit:');
        $timestamp = match ($window) {
            'minute' => (int)(now()->timestamp / 60),
            'hour' => (int)(now()->timestamp / 3600),
            default => (int)(now()->timestamp / 60),
        };

        return "{$prefix}{$identifier}:{$endpoint}:{$window}:{$timestamp}";
    }

    /**
     * Get reset time in seconds for a cache key.
     */
    private function getResetTimeInSeconds(string $cacheKey): int
    {
        try {
            $store = $this->cache->getStore();
            if (method_exists($store, 'getPrefix')) {
                // Attempt to get TTL via the underlying store if supported
                $prefixedKey = $store->getPrefix() . $cacheKey;
                if (method_exists($store, 'getTtl')) {
                    $ttl = $store->getTtl($prefixedKey);
                    return is_int($ttl) ? max(0, $ttl) : 60;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to default
        }
        return 60;
    }

    /**
     * Clear metrics older than specified days.
     */
    public function cleanupMetrics(int $daysOld = 30): int
    {
        return RateLimitMetrics::where('timestamp', '<', now()->subDays($daysOld))->delete();
    }

    /**
     * Clear incidents older than specified days.
     */
    public function cleanupIncidents(int $daysOld = 90): int
    {
        return \Modules\Core\Models\DDoSIncident::where('detected_at', '<', now()->subDays($daysOld))->delete();
    }
}

/**
 * RateLimitResult DTO
 */
class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public int $remaining = 0,
        public int $limit = 0,
        public int $resetInSeconds = 0,
    ) {
    }

    public static function allow(int $remaining = 0, int $limit = 0, int $resetInSeconds = 0): self
    {
        return new self(
            allowed: true,
            remaining: $remaining,
            limit: $limit,
            resetInSeconds: $resetInSeconds,
        );
    }

    public static function deny(int $remaining = 0, int $limit = 0, int $resetInSeconds = 0): self
    {
        return new self(
            allowed: false,
            remaining: $remaining,
            limit: $limit,
            resetInSeconds: $resetInSeconds,
        );
    }
}
