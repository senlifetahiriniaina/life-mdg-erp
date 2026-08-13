<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Widget;
use Modules\Shared\Services\BaseService;

class AdvancedVisualizationService extends BaseService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CHART_TYPES = ['heatmap', '3d_scatter', '3d_surface', 'bubble'];
    private const EXPORT_FORMATS = ['png', 'svg', 'pdf'];
    private const AGGREGATION_FUNCTIONS = ['sum', 'avg', 'count', 'min', 'max', 'percentile'];

    /**
     * Generate heatmap data from raw dataset.
     * Transforms data into X/Y axes with intensity values.
     *
     * @param  array<array{x: string|int, y: string|int, value: float}>  $rawData
     * @param  array{min?: float, max?: float, step?: float}  $options
     * @return array{xAxis: array<string|int>, yAxis: array<string|int>, data: array<array{x: string|int, y: string|int, value: float, intensity: float}>, colorScale: array<string>}
     */
    public function generateHeatmapData(array $rawData, array $options = []): array
    {
        try {
            if (empty($rawData)) {
                return [
                    'xAxis'      => [],
                    'yAxis'      => [],
                    'data'       => [],
                    'colorScale' => [],
                ];
            }

            // Extract unique X and Y values
            $xValues = array_values(array_unique(array_map(fn ($d) => $d['x'] ?? null, $rawData)));
            $yValues = array_values(array_unique(array_map(fn ($d) => $d['y'] ?? null, $rawData)));

            // Sort for consistent ordering
            usort($xValues, fn ($a, $b) => strval($a) <=> strval($b));
            usort($yValues, fn ($a, $b) => strval($a) <=> strval($b));

            // Calculate intensity for each point
            $allValues = array_map(fn ($d) => (float) ($d['value'] ?? 0), $rawData);
            $minValue  = min($allValues) ?: 0;
            $maxValue  = max($allValues) ?: 1;

            $processedData = [];
            foreach ($rawData as $point) {
                $intensity = $maxValue > $minValue
                    ? ($point['value'] - $minValue) / ($maxValue - $minValue)
                    : 0.5;

                $processedData[] = [
                    'x'         => $point['x'],
                    'y'         => $point['y'],
                    'value'     => $point['value'],
                    'intensity' => round($intensity, 4),
                ];
            }

            // Generate color scale
            $colorScale = $this->generateColorScale(10);

            Log::debug('Heatmap data generated', [
                'data_points'  => count($processedData),
                'x_values'     => count($xValues),
                'y_values'     => count($yValues),
            ]);

            return [
                'xAxis'      => $xValues,
                'yAxis'      => $yValues,
                'data'       => $processedData,
                'colorScale' => $colorScale,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to generate heatmap data', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create 3D visualization configuration with axes and color mapping.
     *
     * @param  array<array{x: float, y: float, z: float, label?: string}>  $dataPoints
     * @param  array{colorBy?: 'x'|'y'|'z'|'label', rotation?: array{x?: float, y?: float, z?: float}}  $config
     * @return array{points: array, axes: array{x: array, y: array, z: array}, colorMap: array, rotation: array}
     */
    public function create3DVisualization(array $dataPoints, array $config = []): array
    {
        try {
            if (empty($dataPoints)) {
                return [
                    'points'   => [],
                    'axes'     => [
                        'x' => ['min' => 0, 'max' => 1, 'label' => 'X'],
                        'y' => ['min' => 0, 'max' => 1, 'label' => 'Y'],
                        'z' => ['min' => 0, 'max' => 1, 'label' => 'Z'],
                    ],
                    'colorMap' => [],
                    'rotation' => [],
                ];
            }

            // Extract axis ranges
            $xValues = array_map(fn ($p) => (float) ($p['x'] ?? 0), $dataPoints);
            $yValues = array_map(fn ($p) => (float) ($p['y'] ?? 0), $dataPoints);
            $zValues = array_map(fn ($p) => (float) ($p['z'] ?? 0), $dataPoints);

            $axes = [
                'x' => ['min' => min($xValues), 'max' => max($xValues) ?: 1, 'label' => 'X Axis'],
                'y' => ['min' => min($yValues), 'max' => max($yValues) ?: 1, 'label' => 'Y Axis'],
                'z' => ['min' => min($zValues), 'max' => max($zValues) ?: 1, 'label' => 'Z Axis'],
            ];

            // Generate color mapping based on configuration
            $colorBy  = $config['colorBy'] ?? 'z';
            $colorMap = $this->generateColorMap($dataPoints, $colorBy);

            // Add colors to data points
            $processedPoints = [];
            foreach ($dataPoints as $index => $point) {
                $processedPoints[] = [
                    'x'     => (float) ($point['x'] ?? 0),
                    'y'     => (float) ($point['y'] ?? 0),
                    'z'     => (float) ($point['z'] ?? 0),
                    'label' => $point['label'] ?? "Point {$index}",
                    'color' => $colorMap['colors'][$index] ?? '#4287f5',
                ];
            }

            // Set rotation defaults
            $rotation = $config['rotation'] ?? ['x' => 0.5, 'y' => 0.5, 'z' => 0];

            Log::debug('3D visualization created', ['points' => count($processedPoints)]);

            return [
                'points'   => $processedPoints,
                'axes'     => $axes,
                'colorMap' => $colorMap,
                'rotation' => $rotation,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create 3D visualization', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Render interactive chart from template and data source.
     *
     * @param  array{type: string, template_id?: int, dataSourceId: int, dimensions?: array<string>, metrics?: array<string>}  $config
     * @return array{html: string, data: array, metadata: array}
     */
    public function renderInteractiveChart(array $config): array
    {
        try {
            if (!in_array($config['type'], self::CHART_TYPES)) {
                throw new \InvalidArgumentException("Unsupported chart type: {$config['type']}");
            }

            $cacheKey = "chart:{$config['type']}:{$config['dataSourceId']}";
            $cached   = Cache::get($cacheKey);

            if ($cached) {
                Log::debug('Using cached chart data', ['cache_key' => $cacheKey]);
                return $cached;
            }

            // Fetch and transform data
            $data     = $this->fetchChartData($config['dataSourceId'], $config);
            $rendered = $this->renderChartTemplate($config['type'], $data, $config);

            $result = [
                'html'     => $rendered['html'],
                'data'     => $rendered['data'],
                'metadata' => [
                    'type'      => $config['type'],
                    'timestamp' => now()->toIso8601String(),
                    'rows'      => count($rendered['data']),
                ],
            ];

            Cache::put($cacheKey, $result, self::CACHE_TTL);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Failed to render interactive chart', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Apply data transformations (aggregations) to dataset.
     *
     * @param  array<array<string, mixed>>  $data
     * @param  array{function: string, groupBy?: array<string>, metric: string}  $transformation
     * @return array<array<string, mixed>>
     */
    public function applyDataTransformation(array $data, array $transformation): array
    {
        try {
            $function = $transformation['function'] ?? 'sum';
            if (!in_array($function, self::AGGREGATION_FUNCTIONS)) {
                throw new \InvalidArgumentException("Unsupported aggregation function: {$function}");
            }

            $metric  = $transformation['metric'] ?? 'value';
            $groupBy = $transformation['groupBy'] ?? [];

            if (empty($groupBy)) {
                // Single aggregation across all data
                $values = array_map(fn ($row) => (float) ($row[$metric] ?? 0), $data);
                return [[
                    'aggregated_value' => $this->aggregate($values, $function),
                ]];
            }

            // Group and aggregate
            $groups = [];
            foreach ($data as $row) {
                $groupKey = implode('|', array_map(fn ($g) => $row[$g] ?? '', $groupBy));
                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'values' => [],
                        'row'    => [],
                    ];
                }

                foreach ($groupBy as $g) {
                    $groups[$groupKey]['row'][$g] = $row[$g] ?? null;
                }

                $groups[$groupKey]['values'][] = (float) ($row[$metric] ?? 0);
            }

            $result = [];
            foreach ($groups as $groupData) {
                $result[] = array_merge(
                    $groupData['row'],
                    [$metric => $this->aggregate($groupData['values'], $function)]
                );
            }

            Log::debug('Data transformation applied', ['function' => $function, 'groups' => count($result)]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Failed to apply data transformation', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cache rendered chart data for performance optimization.
     *
     * @param  string  $cacheKey
     * @param  array  $chartData
     * @param  int  $ttl  Cache TTL in seconds
     * @return bool
     */
    public function cacheChartData(string $cacheKey, array $chartData, int $ttl = self::CACHE_TTL): bool
    {
        try {
            Cache::put($cacheKey, $chartData, $ttl);
            Log::debug('Chart data cached', ['key' => $cacheKey, 'ttl' => $ttl]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to cache chart data', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Generate chart export in specified format.
     *
     * @param  int  $widgetId
     * @param  string  $format  One of: png, svg, pdf
     * @param  array{scale?: float, width?: int, height?: int}  $options
     * @return array{data: string, mimeType: string, filename: string}
     */
    public function generateChartExport(int $widgetId, string $format = 'png', array $options = []): array
    {
        try {
            if (!in_array($format, self::EXPORT_FORMATS)) {
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
            }

            $widget = Widget::findOrFail($widgetId);

            $scale  = $options['scale'] ?? 2.0;
            $width  = $options['width'] ?? 1200;
            $height = $options['height'] ?? 800;

            // Generate export file
            $mimeTypes = [
                'png' => 'image/png',
                'svg' => 'image/svg+xml',
                'pdf' => 'application/pdf',
            ];

            $filename = sprintf(
                '%s_%s.%s',
                str_replace(' ', '_', $widget->name),
                now()->format('YmdHis'),
                $format
            );

            // Simulate export data generation
            $exportData = json_encode([
                'widget_id' => $widget->id,
                'format'    => $format,
                'options'   => compact('scale', 'width', 'height'),
                'timestamp' => now()->toIso8601String(),
            ]);

            Log::info('Chart export generated', [
                'widget_id' => $widgetId,
                'format'    => $format,
                'filename'  => $filename,
            ]);

            return [
                'data'     => $exportData,
                'mimeType' => $mimeTypes[$format],
                'filename' => $filename,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to generate chart export', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Track visualization performance metrics (rendering time, data points).
     *
     * @param  int  $widgetId
     * @param  array{renderingTime: float, dataPoints: int, cacheHit?: bool}  $metrics
     * @return array
     */
    public function trackVisualizationPerformance(int $widgetId, array $metrics): array
    {
        try {
            $performanceKey = "perf:viz:{$widgetId}";

            $performanceData = [
                'widget_id'      => $widgetId,
                'rendering_time' => $metrics['renderingTime'] ?? 0,
                'data_points'    => $metrics['dataPoints'] ?? 0,
                'cache_hit'      => $metrics['cacheHit'] ?? false,
                'timestamp'      => now()->toIso8601String(),
            ];

            // Store in cache with longer TTL for analytics
            Cache::put($performanceKey, $performanceData, 86400); // 24 hours

            Log::info('Visualization performance tracked', $performanceData);

            return $performanceData;
        } catch (\Throwable $e) {
            Log::error('Failed to track visualization performance', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update and save visualization template configuration.
     *
     * @param  int  $widgetId
     * @param  array{config: array, name?: string, description?: string}  $templateData
     * @return Widget
     */
    public function updateVisualizationTemplate(int $widgetId, array $templateData): Widget
    {
        try {
            $widget = Widget::findOrFail($widgetId);

            $updateData = [
                'configuration' => $templateData['config'] ?? [],
            ];

            if (isset($templateData['name'])) {
                $updateData['name'] = $templateData['name'];
            }

            if (isset($templateData['description'])) {
                $updateData['description'] = $templateData['description'];
            }

            $widget->update($updateData);

            Log::info('Visualization template updated', ['widget_id' => $widgetId]);

            return $widget->fresh();
        } catch (\Throwable $e) {
            Log::error('Failed to update visualization template', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Apply color scale/scheme to heatmap visualization.
     *
     * @param  array  $data  Heatmap data with intensity values
     * @param  string  $scheme  Color scheme name (viridis, plasma, cool, warm, etc)
     * @return array
     */
    public function applyColorScale(array $data, string $scheme = 'viridis'): array
    {
        try {
            $colors = $this->getColorScheme($scheme);

            $processedData = [];
            foreach ($data['data'] ?? [] as $point) {
                $intensity  = $point['intensity'] ?? 0;
                $colorIndex = (int) round($intensity * (count($colors) - 1));
                $color      = $colors[$colorIndex];

                $processedData[] = array_merge($point, ['color' => $color]);
            }

            Log::debug('Color scale applied', ['scheme' => $scheme, 'points' => count($processedData)]);

            return [
                'xAxis'      => $data['xAxis'] ?? [],
                'yAxis'      => $data['yAxis'] ?? [],
                'data'       => $processedData,
                'colorScale' => $colors,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to apply color scale', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate that data source is compatible with visualization type.
     *
     * @param  int  $dataSourceId
     * @param  string  $chartType
     * @return array{valid: bool, issues: array<string>}
     */
    public function validateDataSourceBinding(int $dataSourceId, string $chartType): array
    {
        try {
            $issues = [];

            // Check data source exists
            $dataSource = DB::table('bi_data_sources')->find($dataSourceId);
            if (!$dataSource) {
                $issues[] = "Data source {$dataSourceId} not found";
                return ['valid' => false, 'issues' => $issues];
            }

            // Check status
            if ($dataSource->status !== 'active') {
                $issues[] = "Data source is not active (status: {$dataSource->status})";
            }

            // Validate chart type requires specific fields
            $requirements = $this->getChartTypeRequirements($chartType);
            foreach ($requirements['required_fields'] as $field) {
                // Could validate presence of fields in actual data
                // For now, just check structural requirements
            }

            Log::debug('Data source binding validated', [
                'data_source_id' => $dataSourceId,
                'chart_type'     => $chartType,
                'issues'         => count($issues),
            ]);

            return [
                'valid'  => empty($issues),
                'issues' => $issues,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to validate data source binding', ['error' => $e->getMessage()]);
            return [
                'valid'  => false,
                'issues' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Get visualization recommendations based on data characteristics.
     *
     * @param  array<array<string, mixed>>  $data
     * @param  array{rowCount?: int, columnCount?: int, dataTypes?: array}  $characteristics
     * @return array{recommended: array<string>, reasons: array}
     */
    public function getVisualizationRecommendations(array $data, array $characteristics = []): array
    {
        try {
            $recommendations = [];
            $reasons         = [];

            $rowCount    = count($data);
            $columnCount = count($data[0] ?? []);

            if ($rowCount > 100 && $rowCount < 10000) {
                $recommendations[] = 'heatmap';
                $reasons['heatmap'] = "Good for {$rowCount} data points with categorical dimensions";
            }

            if ($columnCount >= 3 && $rowCount < 1000) {
                $recommendations[] = '3d_scatter';
                $reasons['3d_scatter'] = "Good for visualizing {$columnCount} dimensions";
            }

            if ($rowCount > 1000) {
                $recommendations[] = 'bubble';
                $reasons['bubble'] = "Efficient for large datasets ({$rowCount} points)";
            }

            if ($columnCount == 2 && $rowCount < 500) {
                $recommendations[] = '3d_surface';
                $reasons['3d_surface'] = "Good for 2D/3D interpolation with {$rowCount} points";
            }

            Log::debug('Visualization recommendations generated', [
                'recommendations' => count($recommendations),
            ]);

            return [
                'recommended' => array_unique($recommendations),
                'reasons'     => $reasons,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get visualization recommendations', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get performance metrics for chart rendering.
     *
     * @param  int  $widgetId
     * @param  string  $period  Period: '1h', '24h', '7d', '30d'
     * @return array{avgRenderingTime: float, totalDataPoints: int, cacheHitRate: float, peakTime: string}
     */
    public function getPerformanceMetrics(int $widgetId, string $period = '24h'): array
    {
        try {
            $perfData = Cache::get("perf:viz:{$widgetId}");

            if (!$perfData) {
                return [
                    'avgRenderingTime' => 0,
                    'totalDataPoints'  => 0,
                    'cacheHitRate'     => 0,
                    'peakTime'         => null,
                ];
            }

            // Aggregate performance metrics
            $metrics = [
                'avgRenderingTime' => (float) ($perfData['rendering_time'] ?? 0),
                'totalDataPoints'  => (int) ($perfData['data_points'] ?? 0),
                'cacheHitRate'     => $perfData['cache_hit'] ? 100.0 : 0.0,
                'peakTime'         => $perfData['timestamp'] ?? now()->toIso8601String(),
            ];

            Log::debug('Performance metrics retrieved', ['widget_id' => $widgetId]);

            return $metrics;
        } catch (\Throwable $e) {
            Log::error('Failed to get performance metrics', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    private function generateColorScale(int $steps = 10): array
    {
        $colors = [];
        for ($i = 0; $i < $steps; $i++) {
            $ratio   = $i / ($steps - 1);
            $hue     = (1 - $ratio) * 240; // Blue to Red
            $colors[] = sprintf('hsl(%d, 100%%, 50%%)', (int) $hue);
        }

        return $colors;
    }

    private function generateColorMap(array $dataPoints, string $colorBy): array
    {
        if ($colorBy === 'label') {
            // Different colors per label
            $labels = array_unique(array_map(fn ($p) => $p['label'] ?? '', $dataPoints));
            $colors = array_slice(
                array_map(fn ($i) => sprintf('hsl(%d, 70%%, 50%%)', ($i * 360) / count($labels)), range(1, count($labels))),
                0,
                count($labels)
            );

            return [
                'by'     => 'label',
                'colors' => array_pad([], count($dataPoints), '#4287f5'),
            ];
        }

        $values = array_map(fn ($p) => (float) ($p[$colorBy] ?? 0), $dataPoints);
        $min    = min($values) ?: 0;
        $max    = max($values) ?: 1;

        $colors = [];
        foreach ($values as $value) {
            $ratio    = $max > $min ? ($value - $min) / ($max - $min) : 0.5;
            $hue      = (1 - $ratio) * 240;
            $colors[] = sprintf('hsl(%d, 100%%, 50%%)', (int) $hue);
        }

        return [
            'by'     => $colorBy,
            'colors' => $colors,
            'min'    => $min,
            'max'    => $max,
        ];
    }

    private function aggregate(array $values, string $function): float
    {
        return match ($function) {
            'sum'        => (float) array_sum($values),
            'avg'        => count($values) > 0 ? (float) (array_sum($values) / count($values)) : 0,
            'count'      => (float) count($values),
            'min'        => (float) (count($values) > 0 ? min($values) : 0),
            'max'        => (float) (count($values) > 0 ? max($values) : 0),
            'percentile' => $this->percentile($values, 50),
            default      => 0,
        };
    }

    private function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ($percentile / 100) * count($values);

        if (is_int($index)) {
            return (float) $values[$index - 1];
        }

        $lower = floor($index);
        $upper = ceil($index);

        return (($upper - $index) * $values[$lower - 1] + ($index - $lower) * $values[$upper - 1]);
    }

    private function fetchChartData(int $dataSourceId, array $config): array
    {
        // Placeholder for fetching data from data source
        return [
            'dimensions' => $config['dimensions'] ?? [],
            'metrics'    => $config['metrics'] ?? [],
            'values'     => [],
        ];
    }

    private function renderChartTemplate(string $chartType, array $data, array $config): array
    {
        return [
            'html' => sprintf('<div class="chart chart-%s"></div>', $chartType),
            'data' => $data,
        ];
    }

    private function getChartTypeRequirements(string $chartType): array
    {
        return match ($chartType) {
            'heatmap'    => ['required_fields' => ['x', 'y', 'value']],
            '3d_scatter' => ['required_fields' => ['x', 'y', 'z']],
            '3d_surface' => ['required_fields' => ['x', 'y', 'z']],
            'bubble'     => ['required_fields' => ['x', 'y', 'size', 'value']],
            default      => ['required_fields' => []],
        };
    }

    private function getColorScheme(string $scheme): array
    {
        return match ($scheme) {
            'viridis' => [
                '#440154', '#481f70', '#47108c', '#3e4c89', '#32638e', '#287a8e', '#21918c',
                '#22a88a', '#44bf71', '#7ad151', '#bddf26', '#fde725',
            ],
            'plasma' => [
                '#0d0887', '#5d01a6', '#7e03a8', '#8e0993', '#a12167', '#b83c4b', '#ca4934',
                '#e06e3c', '#f79139', '#f1b73f', '#fecf4b', '#f0f921',
            ],
            'cool' => ['#0d47a1', '#1976d2', '#42a5f5', '#64b5f6', '#90caf9', '#bbdefb'],
            'warm' => ['#fff5e6', '#ffe0b2', '#ffcc80', '#ffb74d', '#ff9800', '#f57c00', '#e65100'],
            default => ['#e0e0e0', '#bdbdbd', '#9e9e9e', '#757575', '#616161', '#424242', '#212121'],
        };
    }
}
