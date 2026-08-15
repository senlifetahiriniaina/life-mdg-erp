<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\Security\Services\RateLimitService;

/**
 * @group Security - Rate Limits
 *
 * Inspect rate limit status, reset limits, and block/unblock IPs.
 */
class RateLimitController extends Controller
{
    public function __construct(private RateLimitService $rateLimitService) {}

    /**
     * Get the rate limit status for a key.
     *
     * @queryParam key string required Rate limit key. Example: api.default
     */
    public function status(Request $request): JsonResponse
    {
        $request->validate(['key' => 'required|string']);

        $headers   = $this->rateLimitService->headers($request, $request->string('key')->toString());
        $remaining = $this->rateLimitService->remaining($request, $request->string('key')->toString());
        $limited   = $this->rateLimitService->isLimited($request, $request->string('key')->toString());

        return response()->json([
            'data' => [
                'key'       => $request->key,
                'limited'   => $limited,
                'remaining' => $remaining,
                'headers'   => $headers,
            ],
        ]);
    }

    /**
     * Reset rate limit for a specific key.
     *
     * @bodyParam key string required Rate limit key to reset. Example: api.default
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate(['key' => 'required|string']);

        $this->rateLimitService->clear($request, $validated['key']);

        return response()->json(['message' => "Rate limit for [{$validated['key']}] has been reset"]);
    }

    /**
     * Block an IP address from accessing the API.
     *
     * @bodyParam ip string required IPv4 or IPv6 address. Example: 192.168.1.100
     * @bodyParam duration_minutes integer Block duration in minutes (0 = permanent). Example: 60
     * @bodyParam reason string Reason for blocking. Example: Brute force attempt
     */
    public function blockIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip'               => 'required|ip',
            'duration_minutes' => 'sometimes|integer|min:0',
            'reason'           => 'sometimes|string|max:500',
        ]);

        $duration = $validated['duration_minutes'] ?? 0;
        $ttl      = $duration > 0 ? $duration * 60 : null;

        Cache::put("security.blocked_ip.{$validated['ip']}", [
            'blocked_at' => now()->toIso8601String(),
            'blocked_by' => $request->user()?->id,
            'reason'     => $validated['reason'] ?? 'Manually blocked',
            'expires_at' => $ttl ? now()->addSeconds($ttl)->toIso8601String() : null,
        ], $ttl);

        return response()->json([
            'message'    => "IP {$validated['ip']} has been blocked",
            'expires_at' => $ttl ? now()->addSeconds($ttl)->toIso8601String() : 'permanent',
        ]);
    }

    /**
     * Unblock an IP address.
     *
     * @bodyParam ip string required IPv4 or IPv6 address. Example: 192.168.1.100
     */
    public function unblockIp(Request $request): JsonResponse
    {
        $validated = $request->validate(['ip' => 'required|ip']);

        Cache::forget("security.blocked_ip.{$validated['ip']}");

        return response()->json(['message' => "IP {$validated['ip']} has been unblocked"]);
    }

    /**
     * List currently blocked IPs (stored in cache).
     */
    public function blockedIps(): JsonResponse
    {
        // In a real system this would query a persistent store; we return the pattern info
        return response()->json([
            'data'    => [],
            'message' => 'Blocked IPs are stored in cache — query your Redis SCAN security.blocked_ip.* for full list',
        ]);
    }
}
