<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;
use Modules\BI\Models\Widget;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * RenderCustomChartJob
 *
 * Renders charts to image format (PNG/SVG) for reports and exports.
 * Handles various chart types and styling configurations.
 *
 * @property int widget_id The ID of the widget/chart
 * @property string output_format Output format: 'png', 'svg', or 'pdf'
 * @property string job_id Unique identifier for tracking progress
 */
class RenderCustomChartJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $widget_id,
        private readonly string $output_format = 'png'
    ) {
        $this->jobId = uniqid('chart_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting chart rendering', [
                'job_id' => $this->jobId,
                'widget_id' => $this->widget_id,
                'format' => $this->output_format,
                'timestamp' => now()->toIso8601String(),
            ]);

            $widget = Widget::findOrFail($this->widget_id);

            // Generate chart image
            $imagePath = $this->generateChartImage($widget);

            // Store rendered chart
            $storagePath = $this->storeRenderedChart($imagePath, $widget);

            // Update widget with rendered path
            $widget->update([
                'rendered_image_path' => $storagePath,
                'rendered_at' => now(),
            ]);

            Log::info('Chart rendering completed', [
                'job_id' => $this->jobId,
                'widget_id' => $this->widget_id,
                'storage_path' => $storagePath,
                'format' => $this->output_format,
            ]);

            // Clean up temporary file
            @unlink($imagePath);
        } catch (\Throwable $e) {
            Log::error('Chart rendering failed', [
                'job_id' => $this->jobId,
                'widget_id' => $this->widget_id,
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
            throw new Exception("No tenant context available for chart rendering job");
        }

        return (int) $tenantId;
    }

    /**
     * Generate chart image based on widget configuration
     */
    private function generateChartImage(Widget $widget): string
    {
        $config = $widget->config ?? [];
        $chartType = $config['chart_type'] ?? 'line';

        // Create temporary file path
        $tempPath = storage_path('app/temp/' . $this->jobId . '.' . $this->output_format);

        // Ensure temp directory exists
        @mkdir(dirname($tempPath), 0755, true);

        // Generate chart using appropriate method
        switch ($this->output_format) {
            case 'svg':
                $this->renderSvgChart($widget, $chartType, $tempPath);
                break;
            case 'pdf':
                $this->renderPdfChart($widget, $chartType, $tempPath);
                break;
            case 'png':
            default:
                $this->renderPngChart($widget, $chartType, $tempPath);
                break;
        }

        Log::debug('Chart image generated', [
            'job_id' => $this->jobId,
            'temp_path' => $tempPath,
            'size' => filesize($tempPath) . ' bytes',
        ]);

        return $tempPath;
    }

    /**
     * Render chart as PNG
     */
    private function renderPngChart(Widget $widget, string $chartType, string $outputPath): void
    {
        // In a real implementation, use a library like:
        // - Chart.js with headless browser (Puppeteer)
        // - GD library for simpler charts
        // - ImageMagick wrapper

        $svgPath = str_replace('.png', '.svg', $outputPath);
        $this->renderSvgChart($widget, $chartType, $svgPath);

        // Convert SVG to PNG using ImageMagick or similar
        // For now, simulate with a dummy PNG
        $this->createDummyPng($outputPath);
    }

    /**
     * Render chart as SVG (vector format)
     */
    private function renderSvgChart(Widget $widget, string $chartType, string $outputPath): void
    {
        $config = $widget->config ?? [];
        $data = Cache::get("viz_data_{$widget->id}_last_30_days") ?? [];

        $svg = $this->buildSvgMarkup($chartType, $data, $config);

        file_put_contents($outputPath, $svg);
    }

    /**
     * Render chart as PDF
     */
    private function renderPdfChart(Widget $widget, string $chartType, string $outputPath): void
    {
        // In a real implementation, use DOMPDF or similar
        // For now, create a simple PDF structure
        $svgPath = str_replace('.pdf', '.svg', $outputPath);
        $this->renderSvgChart($widget, $chartType, $svgPath);

        // Create simple PDF from SVG (simplified simulation)
        $this->createDummyPdf($outputPath);
    }

    /**
     * Build SVG markup for chart
     *
     * @param array<int, array<string, mixed>> $data
     * @param array<string, mixed> $config
     */
    private function buildSvgMarkup(string $chartType, array $data, array $config): string
    {
        $width = $config['width'] ?? 800;
        $height = $config['height'] ?? 600;
        $title = $config['title'] ?? 'Chart';

        $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg width="$width" height="$height" xmlns="http://www.w3.org/2000/svg">
  <style>
    .chart-title { font-size: 18px; font-weight: bold; }
    .chart-axis { font-size: 12px; }
    .chart-grid { stroke: #e0e0e0; }
  </style>
  <rect width="$width" height="$height" fill="white" stroke="#999"/>
  <text x="20" y="30" class="chart-title">$title</text>
SVG;

        // Add chart-specific elements based on type
        $svg .= match ($chartType) {
            'bar' => $this->buildSvgBarChart($data, $width, $height),
            'line' => $this->buildSvgLineChart($data, $width, $height),
            'pie' => $this->buildSvgPieChart($data, $width, $height),
            'area' => $this->buildSvgAreaChart($data, $width, $height),
            default => $this->buildSvgLineChart($data, $width, $height),
        };

        $svg .= "\n</svg>";

        return $svg;
    }

    /**
     * Build SVG bar chart
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function buildSvgBarChart(array $data, int $width, int $height): string
    {
        $svg = '';
        $barWidth = ($width - 60) / max(1, count($data));
        $maxValue = max(array_map(fn($row) => $row['value'] ?? 0, $data));

        $xOffset = 40;
        foreach ($data as $index => $row) {
            $barHeight = (($row['value'] ?? 0) / max(1, $maxValue)) * ($height - 80);
            $x = $xOffset + ($index * $barWidth);
            $y = $height - 40 - $barHeight;

            $svg .= "<rect x=\"$x\" y=\"$y\" width=\"" . ($barWidth - 2) . "\" height=\"$barHeight\" fill=\"#4CAF50\" stroke=\"#333\"/>\n";
        }

        return $svg;
    }

    /**
     * Build SVG line chart
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function buildSvgLineChart(array $data, int $width, int $height): string
    {
        if (empty($data)) {
            return '';
        }

        $svg = '';
        $pointSpacing = ($width - 60) / max(1, count($data) - 1);
        $maxValue = max(array_map(fn($row) => $row['value'] ?? 0, $data));

        $points = '';
        $xOffset = 40;
        foreach ($data as $index => $row) {
            $x = $xOffset + ($index * $pointSpacing);
            $y = $height - 40 - (($row['value'] ?? 0) / max(1, $maxValue)) * ($height - 80);
            $points .= "$x,$y ";
        }

        $svg .= "<polyline points=\"$points\" fill=\"none\" stroke=\"#2196F3\" stroke-width=\"2\"/>\n";

        // Add data points
        $xOffset = 40;
        foreach ($data as $index => $row) {
            $x = $xOffset + ($index * $pointSpacing);
            $y = $height - 40 - (($row['value'] ?? 0) / max(1, $maxValue)) * ($height - 80);
            $svg .= "<circle cx=\"$x\" cy=\"$y\" r=\"4\" fill=\"#2196F3\"/>\n";
        }

        return $svg;
    }

    /**
     * Build SVG pie chart
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function buildSvgPieChart(array $data, int $width, int $height): string
    {
        // Simplified pie chart
        $total = array_sum(array_map(fn($row) => $row['value'] ?? 0, $data));
        $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8'];

        $svg = '';
        $startAngle = 0;
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = min($width, $height) / 3;

        foreach ($data as $index => $row) {
            $value = $row['value'] ?? 0;
            $sliceAngle = ($value / max(1, $total)) * 360;
            $color = $colors[$index % count($colors)];

            // Create pie slice (simplified)
            $svg .= "<path fill=\"$color\" stroke=\"#fff\" d=\"M $centerX $centerY L " . (int)($centerX + $radius * cos(deg2rad($startAngle))) . " " . (int)($centerY + $radius * sin(deg2rad($startAngle))) . " A $radius $radius 0 0 1 " . (int)($centerX + $radius * cos(deg2rad($startAngle + $sliceAngle))) . " " . (int)($centerY + $radius * sin(deg2rad($startAngle + $sliceAngle))) . " Z\"/>\n";
            $startAngle += $sliceAngle;
        }

        return $svg;
    }

    /**
     * Build SVG area chart
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function buildSvgAreaChart(array $data, int $width, int $height): string
    {
        if (empty($data)) {
            return '';
        }

        $svg = '';
        $pointSpacing = ($width - 60) / max(1, count($data) - 1);
        $maxValue = max(array_map(fn($row) => $row['value'] ?? 0, $data));

        $points = '';
        $xOffset = 40;
        foreach ($data as $index => $row) {
            $x = $xOffset + ($index * $pointSpacing);
            $y = $height - 40 - (($row['value'] ?? 0) / max(1, $maxValue)) * ($height - 80);
            $points .= "$x,$y ";
        }

        // Close area shape
        $points .= ($xOffset + ((count($data) - 1) * $pointSpacing)) . ',' . ($height - 40) . ' ';
        $points .= '40,' . ($height - 40);

        $svg .= "<polygon points=\"$points\" fill=\"#2196F3\" opacity=\"0.3\" stroke=\"#2196F3\" stroke-width=\"2\"/>\n";

        return $svg;
    }

    /**
     * Create a dummy PNG file (for simulation)
     */
    private function createDummyPng(string $outputPath): void
    {
        // Create minimal PNG in binary format
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        file_put_contents($outputPath, $png);
    }

    /**
     * Create a dummy PDF file (for simulation)
     */
    private function createDummyPdf(string $outputPath): void
    {
        // Create minimal PDF structure
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000058 00000 n\n0000000115 00000 n\ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n212\n%%EOF";
        file_put_contents($outputPath, $pdf);
    }

    /**
     * Store rendered chart to permanent storage
     */
    private function storeRenderedChart(string $imagePath, Widget $widget): string
    {
        $timestamp = now()->format('YmdHis');
        $storagePath = "charts/widget_{$widget->id}_{$timestamp}.{$this->output_format}";

        Storage::disk('public')->put($storagePath, file_get_contents($imagePath));

        return $storagePath;
    }
}
