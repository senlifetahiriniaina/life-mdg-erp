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
    // Chantier 32.3: see blockedIps()'s docblock.
    private const BLOCKED_IP_INDEX_KEY = 'security.blocked_ip_index';

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

        // Chantier 32.3: ThreatDetectionService::isKnownThreatIp() (called by
        // the real root request-inspection WAF middleware) now genuinely
        // reads this cache key — see that service's own docblock. This
        // controller's "IP has been blocked" response used to be a false
        // claim: the write happened, nothing ever read it.
        $this->addToBlockedIpIndex($validated['ip']);

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
        $this->removeFromBlockedIpIndex($validated['ip']);

        return response()->json(['message' => "IP {$validated['ip']} has been unblocked"]);
    }

    /**
     * List currently blocked IPs.
     *
     * Chantier 32.3: used to unconditionally return an empty array with a
     * "go SCAN Redis yourself" message — this app's real cache driver is
     * `file` (per config/cache.php), which doesn't support key-pattern
     * scanning at all, so that message was actionable for nobody. A small
     * companion index (a single cache key holding the IP list, maintained
     * by blockIp()/unblockIp() above) works identically on every cache
     * driver this app actually uses; stale entries (a duration-limited
     * block whose per-IP key already expired) are pruned on read here
     * rather than left to accumulate forever.
     */
    public function blockedIps(): JsonResponse
    {
        $index = Cache::get(self::BLOCKED_IP_INDEX_KEY, []);
        $stillBlocked = [];

        foreach ($index as $ip) {
            $entry = Cache::get("security.blocked_ip.{$ip}");
            if ($entry !== null) {
                $stillBlocked[$ip] = $entry;
            }
        }

        if (count($stillBlocked) !== count($index)) {
            Cache::forever(self::BLOCKED_IP_INDEX_KEY, array_keys($stillBlocked));
        }

        return response()->json([
            'data' => collect($stillBlocked)->map(fn ($entry, $ip) => ['ip' => $ip, ...$entry])->values(),
        ]);
    }

    private function addToBlockedIpIndex(string $ip): void
    {
        $index = Cache::get(self::BLOCKED_IP_INDEX_KEY, []);
        if (! in_array($ip, $index, true)) {
            $index[] = $ip;
        }
        Cache::forever(self::BLOCKED_IP_INDEX_KEY, $index);
    }

    private function removeFromBlockedIpIndex(string $ip): void
    {
        $index = array_values(array_diff(Cache::get(self::BLOCKED_IP_INDEX_KEY, []), [$ip]));
        Cache::forever(self::BLOCKED_IP_INDEX_KEY, $index);
    }
}
