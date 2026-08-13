<?php

namespace Modules\BI\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class AdvancedReportBuilderService
{
    const CACHE_TTL = 3600; // 1 hour
    const MAX_REPORT_SIZE = 50000; // 50k rows

    /**
     * Create custom report with filters and aggregations
     */
    public function createReport(array $config): array
    {
        $reportId = uniqid('report_');

        $reportData = [
            'id' => $reportId,
            'name' => $config['name'],
            'data_source' => $config['data_source'],
            'metrics' => $config['metrics'] ?? [],
            'dimensions' => $config['dimensions'] ?? [],
            'filters' => $config['filters'] ?? [],
            'sort_by' => $config['sort_by'] ?? [],
            'limit' => min($config['limit'] ?? 1000, self::MAX_REPORT_SIZE),
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put("report:{$reportId}", $reportData, now()->addDays(30));

        return [
            'report_id' => $reportId,
            'status' => 'created',
            'config' => $reportData,
        ];
    }

    /**
     * Build and execute report query
     */
    public function executeReport(string $reportId): array
    {
        $report = Cache::get("report:{$reportId}");

        if (!$report) {
            return ['error' => 'Report not found', 'status' => 'failed'];
        }

        return Cache::remember("report:data:{$reportId}", self::CACHE_TTL, function () use ($report) {
            return $this->buildQuery($report)->get()->toArray();
        });
    }

    /**
     * Build query from report configuration
     */
    private function buildQuery(array $report)
    {
        $table = $report['data_source'];
        $query = DB::table($table);

        // Apply filters
        foreach ($report['filters'] as $filter) {
            $query = $this->applyFilter($query, $filter);
        }

        // Select dimensions and metrics
        $columns = array_merge($report['dimensions'], $report['metrics']);
        if (!empty($columns)) {
            $query = $query->select($columns);
        }

        // Group by dimensions
        if (!empty($report['dimensions'])) {
            $query = $query->groupBy($report['dimensions']);
        }

        // Apply sorting
        foreach ($report['sort_by'] as $sort) {
            $query = $query->orderBy($sort['column'], $sort['direction'] ?? 'asc');
        }

        // Apply limit
        $query = $query->limit($report['limit']);

        return $query;
    }

    /**
     * Apply filter to query
     */
    private function applyFilter($query, array $filter)
    {
        return match($filter['operator'] ?? 'equals') {
            'equals' => $query->where($filter['column'], $filter['value']),
            'not_equals' => $query->where($filter['column'], '!=', $filter['value']),
            'greater_than' => $query->where($filter['column'], '>', $filter['value']),
            'less_than' => $query->where($filter['column'], '<', $filter['value']),
            'between' => $query->whereBetween($filter['column'], $filter['value']),
            'in' => $query->whereIn($filter['column'], $filter['value']),
            'like' => $query->where($filter['column'], 'like', '%' . $filter['value'] . '%'),
            'null' => $query->whereNull($filter['column']),
            'not_null' => $query->whereNotNull($filter['column']),
            default => $query,
        };
    }

    /**
     * Add metrics aggregation
     */
    public function addMetric(string $reportId, string $metricName, string $column, string $aggregation): array
    {
        $report = Cache::get("report:{$reportId}");

        if (!$report) {
            return ['error' => 'Report not found'];
        }

        $report['metrics'][] = [
            'name' => $metricName,
            'column' => $column,
            'aggregation' => $aggregation, // sum, avg, count, min, max, stddev
        ];

        Cache::put("report:{$reportId}", $report, now()->addDays(30));

        return [
            'report_id' => $reportId,
            'metric' => $report['metrics'][count($report['metrics']) - 1],
            'status' => 'added',
        ];
    }

    /**
     * Add report filter
     */
    public function addFilter(string $reportId, array $filter): array
    {
        $report = Cache::get("report:{$reportId}");

        if (!$report) {
            return ['error' => 'Report not found'];
        }

        $report['filters'][] = $filter;
        Cache::put("report:{$reportId}", $report, now()->addDays(30));

        return [
            'report_id' => $reportId,
            'filter' => $filter,
            'status' => 'added',
        ];
    }

    /**
     * Get report definition
     */
    public function getReport(string $reportId): ?array
    {
        return Cache::get("report:{$reportId}");
    }

    /**
     * Delete report
     */
    public function deleteReport(string $reportId): array
    {
        Cache::forget("report:{$reportId}");
        Cache::forget("report:data:{$reportId}");

        return [
            'report_id' => $reportId,
            'status' => 'deleted',
        ];
    }

    /**
     * Clone report
     */
    public function cloneReport(string $reportId, string $newName): array
    {
        $report = Cache::get("report:{$reportId}");

        if (!$report) {
            return ['error' => 'Report not found'];
        }

        $newReportId = uniqid('report_');
        $report['id'] = $newReportId;
        $report['name'] = $newName;

        Cache::put("report:{$newReportId}", $report, now()->addDays(30));

        return [
            'new_report_id' => $newReportId,
            'status' => 'cloned',
        ];
    }
}
