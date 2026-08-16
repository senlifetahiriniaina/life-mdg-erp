<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TenantRateLimitMiddleware
{
    public function __construct(private RateLimitService $rateLimitService) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Skip rate limiting for health checks and webhooks
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            // For unauthenticated requests, use IP-based limiting
            return $this->checkIpLimit($request, $next);
        }

        $tenantId = tenancy()->id ?? 1;
        $userId = $user->id;
        $tier = $this->getTenantTier($tenantId);

        // Check per-minute limit
        $minuteCheck = $this->rateLimitService->checkTenantLimit($tenantId, $userId, $tier);
        if ($minuteCheck !== false && $minuteCheck['limited'] === true) {
            return $this->rateLimitExceeded($minuteCheck);
        }

        // Check hourly limit
        $hourlyCheck = $this->rateLimitService->checkHourlyLimit($tenantId, $userId, $tier);
        if ($hourlyCheck !== false && $hourlyCheck['limited'] === true) {
            return $this->rateLimitExceeded($hourlyCheck);
        }

        // Check concurrent request limit
        if (! $this->rateLimitService->checkConcurrentLimit($tenantId, $userId, $tier)) {
            return response()->json(
                ['message' => 'Too many concurrent requests. Please wait and try again.'],
                429
            )->header('X-RateLimit-Limit-Exceeded', 'concurrent');
        }

        // Add response headers with rate limit info
        $response = $next($request);

        if ($minuteCheck !== false && isset($minuteCheck['remaining'])) {
            $response->header('X-RateLimit-Limit', $minuteCheck['limit']);
            $response->header('X-RateLimit-Remaining', $minuteCheck['remaining']);
            $response->header('X-RateLimit-Reset', $minuteCheck['reset_at']);
        }

        // Decrement concurrent counter after response
        app()->terminating(function () use ($tenantId, $userId) {
            $this->rateLimitService->decrementConcurrentLimit($tenantId, $userId);
        });

        return $response;
    }

    /**
     * Check IP-based rate limit for unauthenticated requests.
     */
    private function checkIpLimit(Request $request, Closure $next): SymfonyResponse
    {
        $ipLimit = $this->rateLimitService->checkIpLimit($request->ip());

        if ($ipLimit !== false && $ipLimit['limited'] === true) {
            return $this->rateLimitExceeded($ipLimit);
        }

        return $next($request);
    }

    /**
     * Return rate limit exceeded response.
     */
    private function rateLimitExceeded(array $limitInfo): SymfonyResponse
    {
        $response = response()->json(
            [
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $limitInfo['retry_after'] ?? 60,
            ],
            429
        );

        if (isset($limitInfo['retry_after'])) {
            $response->header('Retry-After', $limitInfo['retry_after']);
        }

        return $response;
    }

    /**
     * Determine if request should skip rate limiting.
     */
    private function shouldSkip(Request $request): bool
    {
        $pathsToSkip = [
            '/api/health',
            '/api/metrics',
            '/webhook',
            '/stripe/webhook',
            '/banking/webhook',
        ];

        foreach ($pathsToSkip as $path) {
            if ($request->is($path) || str_starts_with($request->path(), $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get tenant subscription tier (defaults to 'free').
     * In production, fetch from tenant_modules table or subscription service.
     */
    private function getTenantTier(int $tenantId): string
    {
        // TODO: Implement tenant tier fetching from subscription/billing service
        // For now, default to 'free'
        return cache()->remember(
            "tenant_tier:{$tenantId}",
            3600,
            fn () => 'free' // Default tier
        );
    }
}
