<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Development-only middleware that warns when a single request fires more
 * than the configured threshold of SQL queries — a common N+1 signal.
 *
 * Enable by adding to a route group or globally in bootstrap/app.php (local env only).
 * Never enable in production: it has meaningful overhead from the DB listener.
 */
class DetectNPlusOne
{
    private const DEFAULT_THRESHOLD = 20;

    public function handle(Request $request, Closure $next, int $threshold = self::DEFAULT_THRESHOLD): Response
    {
        if (! app()->isLocal()) {
            return $next($request);
        }

        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = [
                'sql'      => $query->sql,
                'duration' => $query->time,
            ];
        });

        /** @var Response $response */
        $response = $next($request);

        $count = count($queries);

        if ($count > $threshold) {
            $route = $request->route()?->getName() ?? $request->path();

            Log::warning('N+1 warning: high query count on request', [
                'route'       => $route,
                'method'      => $request->method(),
                'query_count' => $count,
                'threshold'   => $threshold,
                'top_queries'  => array_slice($queries, 0, 5),
            ]);

            $response->headers->set('X-Query-Count', (string) $count);
            $response->headers->set('X-NPlusOne-Warning', "Query count {$count} exceeds threshold {$threshold}");
        } else {
            $response->headers->set('X-Query-Count', (string) $count);
        }

        return $response;
    }
}
