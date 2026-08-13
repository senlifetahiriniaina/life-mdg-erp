<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the Prometheus /api/metrics endpoint with a bearer token
 * (config('app.metrics_token') / METRICS_TOKEN).
 *
 * Fail-closed in production: if no token is configured, the endpoint is denied
 * rather than exposed. In local/testing it is allowed so developers can scrape
 * without extra setup.
 */
class MetricsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('app.metrics_token');

        if (empty($token)) {
            if (app()->isProduction()) {
                return $this->deny('Metrics endpoint is not configured.');
            }

            return $next($request);
        }

        if (! hash_equals($token, (string) $request->bearerToken())) {
            return $this->deny('Unauthorized.');
        }

        return $next($request);
    }

    private function deny(string $message): Response
    {
        return response($message, Response::HTTP_UNAUTHORIZED, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
