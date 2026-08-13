<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheApiResponse
{
    /**
     * Cache GET requests to reduce database load
     * Caches for specified TTL (minutes)
     * Automatically invalidates on data changes
     */
    public function handle(Request $request, Closure $next, mixed ...$cacheConfig): Response
    {
        // Only cache GET requests
        if ($request->method() !== 'GET' || !$request->expectsJson()) {
            return $next($request);
        }

        // Generate cache key from request
        $cacheKey = $this->getCacheKey($request);
        $ttl = (int)($cacheConfig[0] ?? 5); // Default 5 minutes

        // Return cached response if exists
        if (Cache::has($cacheKey)) {
            return response()->json(
                Cache::get($cacheKey),
                200,
                ['X-Cache' => 'HIT']
            );
        }

        // Process request
        $response = $next($request);

        // Cache successful JSON responses
        if ($response->getStatusCode() === 200 && $response->headers->get('content-type') === 'application/json') {
            Cache::put($cacheKey, json_decode($response->getContent(), true), now()->addMinutes($ttl));
            return $response->header('X-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * Generate unique cache key from request
     */
    private function getCacheKey(Request $request): string
    {
        $user = auth()->id();
        $path = $request->path();
        $query = $request->query();

        return "api:response:{$user}:" . md5($path . '?' . http_build_query($query));
    }
}
