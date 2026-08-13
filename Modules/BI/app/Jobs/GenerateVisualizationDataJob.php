<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\Widget;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * GenerateVisualizationDataJob
 *
 * Pre-computes heatmap/3D visualization data for large datasets.
 * This prevents UI blocking when processing large amounts of data.
 *
 * @property int visualization_id The ID of the visualization widget
 * @property string date_range The date range (e.g., 'last_30_days', 'this_month')
 * @property string job_id Unique identifier for tracking progress
 */
class GenerateVisualizationDataJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $visualization_id,
        private readonly string $date_range = 'last_30_days'
    ) {
        $this->jobId = uniqid('viz_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting visualization data generation', [
                'job_id' => $this->jobId,
                'visualization_id' => $this->visualization_id,
                'date_range' => $this->date_range,
                'timestamp' => now()->toIso8601String(),
            ]);

            $widget = Widget::findOrFail($this->visualization_id);

            $dateRange = $this->parseDateRange($this->date_range);
            $data = $this->fetchAndProcessVisualizationData($widget, $dateRange);

            // Cache the computed data for 12 hours
            $cacheKey = "viz_data_{$this->visualization_id}_{$this->date_range}";
            Cache::put($cacheKey, $data, 12 * 60 * 60);

            // Update widget metadata
            $widget->update([
                'last_computed_at' => now(),
                'data_points_count' => count($data),
            ]);

            Log::info('Visualization data generation completed', [
                'job_id' => $this->jobId,
                'visualization_id' => $this->visualization_id,
                'data_points' => count($data),
                'cached_at' => $cacheKey,
            ]);
        } catch (\Throwable $e) {
            Log::error('Visualization data generation failed', [
                'job_id' => $this->jobId,
                'visualization_id' => $this->visualization_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extractCompanyId(): int
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            throw new Exception("No tenant context available for visualization data generation job");
        }

        return (int) $tenantId;
    }

    /**
     * Parse date range string to start and end dates
     *
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}
     */
    private function parseDateRange(string $range): array
    {
        return match ($range) {
            'today' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'yesterday' => ['start' => now()->subDay()->startOfDay(), 'end' => now()->subDay()->endOfDay()],
            'last_7_days' => ['start' => now()->subDays(7)->startOfDay(), 'end' => now()->endOfDay()],
            'last_30_days' => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
            'this_month' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'last_month' => ['start' => now()->subMonth()->startOfMonth(), 'end' => now()->subMonth()->endOfMonth()],
            'this_quarter' => ['start' => now()->startOfQuarter(), 'end' => now()->endOfQuarter()],
            'this_year' => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            default => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
        };
    }

    /**
     * Fetch and process data for visualization with chunking for large datasets
     *
     * @param array{start: \Carbon\Carbon, end: \Carbon\Carbon} $dateRange
     * @return array<int, array<string, mixed>>
     */
    private function fetchAndProcessVisualizationData(Widget $widget, array $dateRange): array
    {
        $config = $widget->config ?? [];
        $data = [];
        $chunkSize = 100;

        // Simulate fetching data in chunks
        // In a real implementation, this would query actual data from the configured source
        $totalRecords = $this->estimateRecordCount($widget, $dateRange);

        for ($offset = 0; $offset < $totalRecords; $offset += $chunkSize) {
            $chunk = $this->fetchDataChunk($widget, $dateRange, $offset, $chunkSize);

            // Transform data based on visualization type
            $transformedChunk = $this->transformDataForVisualization($chunk, $config);

            $data = array_merge($data, $transformedChunk);

            // Log progress every 500 records
            if (($offset + $chunkSize) % 500 === 0) {
                Log::info('Visualization data generation progress', [
                    'job_id' => $this->jobId,
                    'processed_records' => min($offset + $chunkSize, $totalRecords),
                    'total_records' => $totalRecords,
                ]);
            }
        }

        return $data;
    }

    /**
     * Estimate total records within date range (simplified)
     */
    private function estimateRecordCount(Widget $widget, array $dateRange): int
    {
        // In a real implementation, this would query the actual data source
        return 250;
    }

    /**
     * Fetch a chunk of data from the data source
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchDataChunk(Widget $widget, array $dateRange, int $offset, int $limit): array
    {
        // In a real implementation, this would fetch from configured data source
        // For now, return mock data structure
        $chunk = [];

        for ($i = 0; $i < $limit; $i++) {
            $chunk[] = [
                'id' => $offset + $i,
                'timestamp' => $dateRange['start']->addMinutes(rand(0, $dateRange['end']->diffInMinutes($dateRange['start'])))->toIso8601String(),
                'value' => rand(10, 1000),
                'category' => 'category_' . rand(1, 5),
            ];
        }

        return $chunk;
    }

    /**
     * Transform raw data into visualization-appropriate format
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>
     */
    private function transformDataForVisualization(array $data, array $config): array
    {
        $vizType = $config['type'] ?? 'chart';

        return match ($vizType) {
            'heatmap' => $this->transformToHeatmap($data),
            '3d' => $this->transformTo3D($data),
            'scatter' => $this->transformToScatter($data),
            default => $data,
        };
    }

    /**
     * Transform data for heatmap visualization
     *
     * @param array<int, array<string, mixed>> $data
     * @return array<int, array<string, mixed>>
     */
    private function transformToHeatmap(array $data): array
    {
        return array_map(fn(array $row) => [
            'x' => $row['category'],
            'y' => date('H', strtotime($row['timestamp'])),
            'value' => $row['value'],
        ], $data);
    }

    /**
     * Transform data for 3D visualization
     *
     * @param array<int, array<string, mixed>> $data
     * @return array<int, array<string, mixed>>
     */
    private function transformTo3D(array $data): array
    {
        return array_map(fn(array $row) => [
            'x' => $row['category'],
            'y' => date('H', strtotime($row['timestamp'])),
            'z' => $row['value'],
            'color' => $row['value'],
        ], $data);
    }

    /**
     * Transform data for scatter plot
     *
     * @param array<int, array<string, mixed>> $data
     * @return array<int, array<string, mixed>>
     */
    private function transformToScatter(array $data): array
    {
        return array_map(fn(array $row) => [
            'x' => rand(1, 100),
            'y' => $row['value'],
            'size' => rand(5, 20),
            'label' => $row['category'],
        ], $data);
    }
}
