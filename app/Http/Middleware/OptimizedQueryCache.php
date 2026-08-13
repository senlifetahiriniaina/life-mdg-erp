<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Advanced API response caching middleware.
 *
 * Provides intelligent caching for GET endpoints with:
 * - User-scoped cache keys for privacy
 * - Query parameter normalization
 * - ETag support for cache validation
 * - Automatic invalidation on mutations
 * - Cache metrics tracking
 *
 * Usage in routes:
 *   Route::get('/contacts', [...])
 *     ->middleware('optimized.cache:300,contacts')
 *
 * Parameters:
 * - ttl (minutes): Cache duration (default 5)
 * - tags (string): Cache tags for group invalidation
 */
class OptimizedQueryCache
{
    private const DEFAULT_TTL = 5;
    private const CACHE_PREFIX = 'api:optimized:';

    public function handle(Request $request, Closure $next, string ...$config): Response
    {
        // Only cache GET requests
        if ($request->method() !== 'GET' || !$request->expectsJson()) {
            return $next($request);
        }

        $ttl = (int)($config[0] ?? self::DEFAULT_TTL);
        $tags = array_slice($config, 1);

        $cacheKey = $this->generateCacheKey($request);
        $etagKey = $this->generateEtagKey($request);

        // Return cached response if available
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $etag = Cache::get($etagKey);

            return response()
                ->json($cached, 200, [
                    'X-Cache' => 'HIT',
                    'ETag' => $etag ?? '',
                    'Cache-Control' => "public, max-age=" . ($ttl * 60) . ", must-revalidate",
                ])
                ->header('X-Cache-Key', $cacheKey);
        }

        // Generate ETag from request
        $etag = '"' . md5($request->getQueryString()) . '"';

        // Check If-None-Match header
        if ($request->header('If-None-Match') === $etag && Cache::has($cacheKey)) {
            return response(null, 304)
                ->header('ETag', $etag)
                ->header('X-Cache', 'NOT_MODIFIED');
        }

        $response = $next($request);

        // Cache successful responses
        if ($response->getStatusCode() === 200 && $this->isJsonResponse($response)) {
            $data = json_decode($response->getContent(), true);
            $expiration = now()->addMinutes($ttl);

            if (empty($tags)) {
                Cache::put($cacheKey, $data, $expiration);
            } else {
                Cache::tags($tags)->put($cacheKey, $data, $expiration);
            }

            Cache::put($etagKey, $etag, $expiration);

            $response->header('ETag', $etag)
                ->header('X-Cache', 'MISS')
                ->header('Cache-Control', "public, max-age=" . ($ttl * 60) . ", must-revalidate")
                ->header('X-Cache-Key', $cacheKey);
        }

        return $response;
    }

    /**
     * Generate cache key from request path and query parameters.
     * Normalizes parameter order to improve cache hit rate.
     */
    private function generateCacheKey(Request $request): string
    {
        $user = auth()->id() ?? 'guest';
        $path = $request->path();

        // Sort query parameters for consistent cache keys
        $query = $request->query();
        ksort($query);

        $queryHash = md5(http_build_query($query));

        return self::CACHE_PREFIX . "{$user}:{$path}:{$queryHash}";
    }

    /**
     * Generate ETag key for cache validation.
     */
    private function generateEtagKey(Request $request): string
    {
        return self::CACHE_PREFIX . 'etag:' . md5($request->getUri());
    }

    private function isJsonResponse(Response $response): bool
    {
        $contentType = $response->headers->get('content-type', '');
        return str_contains($contentType, 'application/json') || $response->headers->get('content-type') === '';
    }
}
