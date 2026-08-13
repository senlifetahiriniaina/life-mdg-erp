<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PerformanceMonitoringService
{
    protected static array $metrics = [];
    protected static float $startTime;

    public static function startTimer(): void
    {
        self::$startTime = microtime(true);
    }

    public static function recordMetric(string $name, float $value, string $unit = 'ms'): void
    {
        $key = "perf.metric.{$name}";
        $current = Cache::get($key, []);
        $current[] = [
            'value' => $value,
            'unit' => $unit,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only last 100 measurements
        $current = array_slice($current, -99);
        Cache::put($key, $current, 86400); // 24 hours
    }

    public static function getMetricStats(string $name): array
    {
        $key = "perf.metric.{$name}";
        $measurements = Cache::get($key, []);

        if (empty($measurements)) {
            return [];
        }

        $values = array_column($measurements, 'value');

        return [
            'name' => $name,
            'count' => count($values),
            'avg' => round(array_sum($values) / count($values), 2),
            'min' => min($values),
            'max' => max($values),
            'p95' => self::percentile($values, 95),
            'p99' => self::percentile($values, 99),
        ];
    }

    public static function recordApiCall(string $endpoint, string $method, float $responseTime, int $statusCode): void
    {
        $key = "perf.api.{$method}.{$endpoint}";
        $stats = Cache::get($key, [
            'calls' => 0,
            'total_time' => 0,
            'errors' => 0,
            'last_error' => null,
        ]);

        $stats['calls']++;
        $stats['total_time'] += $responseTime;

        if ($statusCode >= 400) {
            $stats['errors']++;
            $stats['last_error'] = now()->toIso8601String();
        }

        Cache::put($key, $stats, 86400);
    }

    public static function getApiStats(string $endpoint = null): array
    {
        $pattern = $endpoint ? "*perf.api.*{$endpoint}*" : "*perf.api.*";
        $keys = Cache::getStore()->connection()->keys($pattern);

        $stats = [];
        foreach ($keys as $key) {
            $data = Cache::get($key);
            $stats[str_replace('perf.api.', '', $key)] = [
                'calls' => $data['calls'] ?? 0,
                'avg_time' => $data['calls'] > 0 ? round($data['total_time'] / $data['calls'], 2) : 0,
                'error_rate' => $data['calls'] > 0 ? round(($data['errors'] / $data['calls']) * 100, 2) : 0,
            ];
        }

        return $stats;
    }

    public static function recordDatabaseQuery(string $sql, float $time, int $rows): void
    {
        $hash = md5($sql);
        $key = "perf.query.{$hash}";

        $stats = Cache::get($key, [
            'sql' => $sql,
            'executions' => 0,
            'total_time' => 0,
            'avg_rows' => 0,
        ]);

        $stats['executions']++;
        $stats['total_time'] += $time;
        $stats['avg_rows'] = round(($stats['avg_rows'] * ($stats['executions'] - 1) + $rows) / $stats['executions'], 0);

        Cache::put($key, $stats, 86400);
    }

    public static function getSlowQueries(float $thresholdMs = 1000): array
    {
        $pattern = "*perf.query.*";
        $keys = Cache::getStore()->connection()->keys($pattern);

        $slowQueries = [];
        foreach ($keys as $key) {
            $data = Cache::get($key);
            if ($data['total_time'] / $data['executions'] > $thresholdMs) {
                $slowQueries[] = [
                    'sql' => $data['sql'],
                    'executions' => $data['executions'],
                    'avg_time' => round($data['total_time'] / $data['executions'], 2),
                    'total_time' => round($data['total_time'], 2),
                ];
            }
        }

        usort($slowQueries, fn($a, $b) => $b['avg_time'] <=> $a['avg_time']);

        return array_slice($slowQueries, 0, 20);
    }

    public static function getHealthReport(): array
    {
        $report = [];

        // Cache health
        try {
            Cache::put('health_check', true, 60);
            $report['cache'] = 'healthy';
        } catch (\Exception $e) {
            $report['cache'] = 'degraded';
        }

        // Database health
        try {
            \DB::select('SELECT 1');
            $report['database'] = 'healthy';
        } catch (\Exception $e) {
            $report['database'] = 'degraded';
        }

        // Search health
        try {
            $searchService = app(SearchService::class);
            $report['search'] = $searchService->health() ? 'healthy' : 'degraded';
        } catch (\Exception $e) {
            $report['search'] = 'unavailable';
        }

        // Queue health
        try {
            $queueSize = \Queue::size();
            $report['queue'] = $queueSize < 1000 ? 'healthy' : 'degraded';
        } catch (\Exception $e) {
            $report['queue'] = 'unavailable';
        }

        return array_merge($report, [
            'timestamp' => now()->toIso8601String(),
            'uptime' => floor((time() - \Cache::get('app_started_at', time())) / 60) . ' minutes',
        ]);
    }

    public static function recordMemoryUsage(): void
    {
        $memory = memory_get_usage(true) / 1024 / 1024; // MB
        self::recordMetric('memory_usage', $memory, 'MB');

        if ($memory > 256) { // Alert if over 256MB
            \Log::warning('High memory usage detected', ['memory_mb' => $memory]);
        }
    }

    protected static function percentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ceil((count($values) * $percentile) / 100) - 1;
        return (float) $values[max(0, $index)];
    }
}
