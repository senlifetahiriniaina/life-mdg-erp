<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitService
{
    private RateLimiter $limiter;
    private string $cacheStore;
    private string $keyPrefix;

    public function __construct()
    {
        $this->limiter = app(RateLimiter::class);
        $this->cacheStore = config('rate_limit.cache_store', 'redis');
        $this->keyPrefix = config('rate_limit.key_prefix', 'rate_limit:');
    }

    /**
     * Get rate limit for a tenant based on their subscription tier.
     */
    public function getTenantLimits(int $tenantId, string $tier = 'free'): array
    {
        $tiers = config('rate_limit.tenant_tiers', []);
        $tier = strtolower($tier);

        if (! isset($tiers[$tier])) {
            Log::warning("Unknown rate limit tier: {$tier}, defaulting to free");
            $tier = 'free';
        }

        return $tiers[$tier];
    }

    /**
     * Check if user has exceeded rate limit for their tenant.
     * Returns remaining requests or false if limit exceeded.
     */
    public function checkTenantLimit(int $tenantId, int $userId, string $tier = 'free'): array|bool
    {
        $limits = $this->getTenantLimits($tenantId, $tier);
        $key = $this->getTenantKey($tenantId, $userId);

        $perMinute = $limits['requests_per_minute'];
        $attempts = $this->limiter->attempts($key);

        if ($attempts >= $perMinute) {
            return [
                'limited' => true,
                'retry_after' => $this->limiter->availableIn($key),
                'limit' => $perMinute,
                'current' => $attempts,
            ];
        }

        $this->limiter->hit($key, 60); // 60-second window

        return [
            'limited' => false,
            'remaining' => max(0, $perMinute - $attempts - 1),
            'limit' => $perMinute,
            'reset_at' => $this->limiter->availableIn($key),
        ];
    }

    /**
     * Check hourly rate limit.
     */
    public function checkHourlyLimit(int $tenantId, int $userId, string $tier = 'free'): array|bool
    {
        $limits = $this->getTenantLimits($tenantId, $tier);
        $key = $this->getTenantKey($tenantId, $userId) . ':hourly';

        $perHour = $limits['requests_per_hour'];
        $attempts = $this->limiter->attempts($key);

        if ($attempts >= $perHour) {
            return [
                'limited' => true,
                'retry_after' => $this->limiter->availableIn($key),
                'limit' => $perHour,
                'current' => $attempts,
            ];
        }

        $this->limiter->hit($key, 3600); // 1-hour window

        return [
            'limited' => false,
            'remaining' => max(0, $perHour - $attempts - 1),
            'limit' => $perHour,
            'reset_at' => $this->limiter->availableIn($key),
        ];
    }

    /**
     * Check concurrent request limit.
     */
    public function checkConcurrentLimit(int $tenantId, int $userId, string $tier = 'free'): bool
    {
        $limits = $this->getTenantLimits($tenantId, $tier);
        $key = $this->getConcurrentKey($tenantId, $userId);

        $concurrent = Cache::get($key, 0);

        if ($concurrent >= $limits['concurrent_requests']) {
            return false;
        }

        Cache::increment($key, 1);
        Cache::put($key, $concurrent + 1, 60); // Auto-cleanup after 60 seconds

        return true;
    }

    /**
     * Decrement concurrent request counter when request completes.
     */
    public function decrementConcurrentLimit(int $tenantId, int $userId): void
    {
        $key = $this->getConcurrentKey($tenantId, $userId);
        Cache::decrement($key, 1);
    }

    /**
     * Check IP-based rate limit for public endpoints.
     */
    public function checkIpLimit(string $ip): array|bool
    {
        if (! config('rate_limit.ip_limits.enabled', true)) {
            return ['limited' => false];
        }

        // Check whitelist
        $whitelist = array_filter(explode(',', config('rate_limit.ip_limits.whitelist', '')));
        if (in_array($ip, $whitelist)) {
            return ['limited' => false];
        }

        $key = $this->keyPrefix . 'ip:' . $ip;
        $limit = config('rate_limit.ip_limits.requests_per_minute', 100);
        $attempts = $this->limiter->attempts($key);

        if ($attempts > $limit) {
            return [
                'limited' => true,
                'retry_after' => $this->limiter->availableIn($key),
                'limit' => $limit,
            ];
        }

        $this->limiter->hit($key, 60);

        return [
            'limited' => false,
            'remaining' => max(0, $limit - $attempts - 1),
            'limit' => $limit,
        ];
    }

    /**
     * Reset rate limit for a user (admin action).
     */
    public function resetLimit(int $tenantId, int $userId): void
    {
        $key = $this->getTenantKey($tenantId, $userId);
        Cache::forget($key);
        Cache::forget($key . ':hourly');
        Cache::forget($this->getConcurrentKey($tenantId, $userId));

        Log::info("Rate limit reset for tenant {$tenantId}, user {$userId}");
    }

    /**
     * Get rate limit stats for monitoring.
     */
    public function getStats(int $tenantId, int $userId): array
    {
        $key = $this->getTenantKey($tenantId, $userId);

        return [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'current_minute_requests' => $this->limiter->attempts($key),
            'current_hour_requests' => $this->limiter->attempts($key . ':hourly'),
            'concurrent_requests' => Cache::get($this->getConcurrentKey($tenantId, $userId), 0),
        ];
    }

    /**
     * Generate cache key for tenant rate limiting.
     */
    private function getTenantKey(int $tenantId, int $userId): string
    {
        return "{$this->keyPrefix}tenant:{$tenantId}:user:{$userId}";
    }

    /**
     * Generate cache key for concurrent request tracking.
     */
    private function getConcurrentKey(int $tenantId, int $userId): string
    {
        return "{$this->keyPrefix}concurrent:tenant:{$tenantId}:user:{$userId}";
    }
}
