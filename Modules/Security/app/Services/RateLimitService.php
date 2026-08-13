<?php

declare(strict_types=1);

namespace Modules\Security\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate Limiting Service
 * Implements per-user, per-IP, and per-API-key rate limiting.
 */
class RateLimitService
{
    // Rate limits configuration
    private const LIMITS = [
        'api' => [
            'default' => 100,
            'authenticated' => 300,
            'premium' => 1000,
        ],
        'auth' => [
            'login' => 5,
            'password_reset' => 3,
            'register' => 10,
        ],
    ];

    /**
     * Check if request exceeds rate limit
     */
    public function isLimited(Request $request, string $key): bool
    {
        $key = $this->buildKey($request, $key);
        $limit = $this->getLimit($request, $key);

        return RateLimiter::tooManyAttempts($key, $limit);
    }

    /**
     * Increment rate limit counter
     */
    public function hit(Request $request, string $key, int $decay = 60): int
    {
        $key = $this->buildKey($request, $key);
        return RateLimiter::hit($key, $decay);
    }

    /**
     * Get remaining attempts
     */
    public function remaining(Request $request, string $key): int
    {
        $key = $this->buildKey($request, $key);
        $limit = $this->getLimit($request, $key);
        $attempts = RateLimiter::attempts($key);

        return max(0, $limit - $attempts);
    }

    /**
     * Clear rate limit for user
     */
    public function clear(Request $request, string $key): void
    {
        $key = $this->buildKey($request, $key);
        RateLimiter::clear($key);
    }

    /**
     * Get rate limit headers
     */
    public function headers(Request $request, string $key): array
    {
        $remaining = $this->remaining($request, $key);
        $limit = $this->getLimit($request, $key);

        return [
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) RateLimiter::availableAt($this->buildKey($request, $key)),
        ];
    }

    /**
     * Get appropriate rate limit based on user tier
     */
    private function getLimit(Request $request, string $key): int
    {
        $user = $request->user();

        // Check if using API key
        if ($apiKey = $request->header('X-API-Key')) {
            $tier = $this->getApiKeyTier($apiKey);
            return self::LIMITS['api'][$tier] ?? self::LIMITS['api']['default'];
        }

        // Check user tier
        if ($user) {
            $tier = $user->subscription_tier ?? 'authenticated';
            return self::LIMITS['api'][$tier] ?? self::LIMITS['api']['authenticated'];
        }

        return self::LIMITS['api']['default'];
    }

    /**
     * Get API key tier
     */
    private function getApiKeyTier(string $apiKey): string
    {
        $cached = Cache::get("api_key_tier:{$apiKey}");
        if ($cached) {
            return $cached;
        }

        $tier = DB::table('api_keys')
            ->where('key_hash', hash('sha256', $apiKey))
            ->value('tier') ?? 'default';

        Cache::put("api_key_tier:{$apiKey}", $tier, 3600);

        return $tier;
    }

    /**
     * Build unique rate limit key
     */
    private function buildKey(Request $request, string $key): string
    {
        $identifier = $request->user()?->id
            ?? $request->header('X-API-Key')
            ?? $request->ip();

        return "rate_limit:{$key}:{$identifier}";
    }
}
