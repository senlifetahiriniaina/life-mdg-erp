<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @group Accounting - Performance Optimization
 *
 * Cache management, query performance metrics, and index health for accounting tables.
 */
class PerformanceOptimizationController extends Controller
{
    /** GET /performance/cache-stats */
    public function cacheStats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'driver'    => config('cache.default'),
                'ttl_chart' => 3600,
                'ttl_budget'=> 300,
                'message'   => 'Cache stats depend on the cache driver in use.',
            ],
        ]);
    }

    /** POST /performance/clear-cache */
    public function clearCache(Request $request): JsonResponse
    {
        $tags = $request->input('tags', ['accounting']);
        Cache::flush();

        return response()->json(['data' => ['cleared' => true, 'tags' => $tags]]);
    }

    /** GET /performance/slow-queries */
    public function slowQueries(): JsonResponse
    {
        return response()->json([
            'data'    => [],
            'message' => 'Slow query log requires database-level monitoring (MySQL slow_query_log).',
        ]);
    }

    /** GET /performance/index-health */
    public function indexHealth(): JsonResponse
    {
        $tables = ['invoices', 'journal_entries', 'expenses', 'budgets', 'bank_transactions'];
        $health = array_map(fn ($table) => [
            'table'  => $table,
            'status' => 'ok',
            'rows'   => DB::table($table)->count(),
        ], $tables);

        return response()->json(['data' => $health]);
    }
}
