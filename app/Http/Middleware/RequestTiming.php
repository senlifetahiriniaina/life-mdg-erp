<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects X-Request-Duration-Ms on every response and accumulates
 * per-route latency buckets in Redis for the /api/metrics endpoint.
 */
class RequestTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $response->headers->set('X-Request-Duration-Ms', (string) $durationMs);

        // Accumulate into Redis buckets (best-effort — never block the response)
        if (config('cache.default') === 'redis' && $request->is('api/*')) {
            try {
                $bucket = $this->routeBucket($request);
                $key    = "metrics:http:{$bucket}";

                Cache::store('redis')->remember("{$key}:count", 3600, fn () => 0);
                Cache::store('redis')->increment("{$key}:count");
                Cache::store('redis')->remember("{$key}:sum_ms", 3600, fn () => 0);
                Cache::store('redis')->increment("{$key}:sum_ms", $durationMs);

                if ($durationMs > (int) Cache::store('redis')->get("{$key}:max_ms", 0)) {
                    Cache::store('redis')->put("{$key}:max_ms", $durationMs, 3600);
                }
            } catch (\Throwable) {
                // Non-fatal — metrics accumulation must never break the response
            }
        }

        return $response;
    }

    private function routeBucket(Request $request): string
    {
        $route  = $request->route()?->getName() ?? 'unknown';
        $method = strtolower($request->method());

        // Collapse numeric IDs so distinct resources don't create unbounded keys
        $route = preg_replace('/\.\d+/', '.{id}', $route) ?? $route;

        return "{$method}.{$route}";
    }
}
