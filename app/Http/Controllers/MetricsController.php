<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Exposes application metrics in Prometheus text format.
 *
 * Scraped by Prometheus at /api/metrics. Authentication is enforced by the
 * MetricsToken middleware (Bearer METRICS_TOKEN), not in this controller.
 *
 * @unauthenticated
 */
class MetricsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $lines = array_merge(
            $this->appMetrics(),
            $this->databaseMetrics(),
            $this->queueMetrics(),
            $this->cacheMetrics(),
            $this->httpMetrics(),
            $this->moduleMetrics(),
        );

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function appMetrics(): array
    {
        return [
            '# HELP widehalo_up Application is running',
            '# TYPE widehalo_up gauge',
            'widehalo_up 1',
            '# HELP widehalo_info Application version info',
            '# TYPE widehalo_info gauge',
            sprintf('widehalo_info{version="%s",env="%s"} 1', config('app.version', '1.0.0'), config('app.env')),
        ];
    }

    private function databaseMetrics(): array
    {
        $lines = [
            '# HELP widehalo_db_up Database connectivity (1=up, 0=down)',
            '# TYPE widehalo_db_up gauge',
        ];

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latencyMs = (microtime(true) - $start) * 1000;

            $lines[] = 'widehalo_db_up 1';
            $lines[] = '# HELP widehalo_db_query_latency_ms Last DB ping latency in ms';
            $lines[] = '# TYPE widehalo_db_query_latency_ms gauge';
            $lines[] = sprintf('widehalo_db_query_latency_ms %.2f', $latencyMs);

            // Row counts for key tables
            $counts = Cache::remember('metrics.table_counts', 60, function () {
                return [
                    'users'        => DB::table('users')->count(),
                    'crm_contacts' => $this->safeCount('crm_contacts'),
                    'acc_invoices' => $this->safeCount('acc_invoices'),
                    'hr_employees' => $this->safeCount('hr_employees'),
                ];
            });

            $lines[] = '# HELP widehalo_table_rows Row count per table';
            $lines[] = '# TYPE widehalo_table_rows gauge';
            foreach ($counts as $table => $count) {
                $lines[] = sprintf('widehalo_table_rows{table="%s"} %d', $table, $count);
            }
        } catch (\Throwable) {
            $lines[] = 'widehalo_db_up 0';
        }

        return $lines;
    }

    private function queueMetrics(): array
    {
        $lines = [
            '# HELP widehalo_queue_pending_jobs Pending jobs in the default queue',
            '# TYPE widehalo_queue_pending_jobs gauge',
            '# HELP widehalo_queue_failed_jobs Total failed jobs',
            '# TYPE widehalo_queue_failed_jobs gauge',
        ];

        try {
            $pending = \Illuminate\Support\Facades\Queue::size();
            $failed  = DB::table('failed_jobs')->count();
            $lines[] = "widehalo_queue_pending_jobs {$pending}";
            $lines[] = "widehalo_queue_failed_jobs {$failed}";
        } catch (\Throwable) {
            $lines[] = 'widehalo_queue_pending_jobs -1';
            $lines[] = 'widehalo_queue_failed_jobs -1';
        }

        return $lines;
    }

    private function cacheMetrics(): array
    {
        $lines = [
            '# HELP widehalo_redis_up Redis connectivity (1=up, 0=down)',
            '# TYPE widehalo_redis_up gauge',
        ];

        try {
            $start = microtime(true);
            Redis::ping();
            $latencyMs = (microtime(true) - $start) * 1000;

            $lines[] = 'widehalo_redis_up 1';
            $lines[] = '# HELP widehalo_redis_ping_latency_ms Redis ping latency in ms';
            $lines[] = '# TYPE widehalo_redis_ping_latency_ms gauge';
            $lines[] = sprintf('widehalo_redis_ping_latency_ms %.2f', $latencyMs);
        } catch (\Throwable) {
            $lines[] = 'widehalo_redis_up 0';
        }

        return $lines;
    }

    private function httpMetrics(): array
    {
        $lines = [
            '# HELP widehalo_http_requests_total Total HTTP requests per route',
            '# TYPE widehalo_http_requests_total counter',
            '# HELP widehalo_http_request_duration_ms_avg Average HTTP request duration per route',
            '# TYPE widehalo_http_request_duration_ms_avg gauge',
            '# HELP widehalo_http_request_duration_ms_max Max HTTP request duration per route',
            '# TYPE widehalo_http_request_duration_ms_max gauge',
        ];

        try {
            $redis  = Redis::connection();
            $prefix = config('database.redis.options.prefix', '');
            $pattern = $prefix . 'metrics:http:*:count';
            $keys   = $redis->keys($pattern);

            foreach ($keys as $rawKey) {
                $key = str_replace($prefix, '', $rawKey);
                // key format: metrics:http:{bucket}:count
                $bucket  = substr($key, strlen('metrics:http:'), -strlen(':count'));
                $count   = (int) $redis->get($rawKey);
                $sumMs   = (int) ($redis->get($prefix . "metrics:http:{$bucket}:sum_ms") ?: 0);
                $maxMs   = (int) ($redis->get($prefix . "metrics:http:{$bucket}:max_ms") ?: 0);
                $avgMs   = $count > 0 ? round($sumMs / $count, 2) : 0;

                $label = sprintf('route="%s"', addslashes($bucket));
                $lines[] = "widehalo_http_requests_total{{$label}} {$count}";
                $lines[] = "widehalo_http_request_duration_ms_avg{{$label}} {$avgMs}";
                $lines[] = "widehalo_http_request_duration_ms_max{{$label}} {$maxMs}";
            }
        } catch (\Throwable) {
            // Redis unavailable — skip HTTP metrics silently
        }

        return $lines;
    }

    private function moduleMetrics(): array
    {
        $modules = [
            'CRM', 'HR', 'Inventory', 'Accounting', 'Manufacturing',
            'POS', 'Ecommerce', 'BI', 'Email', 'Documents',
            'Helpdesk', 'Projects', 'WhatsApp',
        ];

        $lines = [
            '# HELP widehalo_module_enabled Module activation state (1=enabled, 0=disabled)',
            '# TYPE widehalo_module_enabled gauge',
        ];

        $manager = app('erp.modules');
        foreach ($modules as $module) {
            $enabled = $manager->isEnabled($module) ? 1 : 0;
            $lines[] = sprintf('widehalo_module_enabled{module="%s"} %d', strtolower($module), $enabled);
        }

        return $lines;
    }

    private function safeCount(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return -1;
        }
    }
}
