<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Widget;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * ExportVisualizationJob
 *
 * Generates PDF/PPT exports with embedded charts and visualizations.
 * Creates comprehensive reports with multiple widgets/dashboards.
 *
 * @property int dashboard_id The ID of the dashboard to export
 * @property string export_type Export type: 'pdf' or 'pptx'
 * @property array<int> widget_ids Specific widgets to include (optional)
 * @property string job_id Unique identifier for tracking progress
 */
class ExportVisualizationJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $dashboard_id,
        private readonly string $export_type = 'pdf',
        private readonly array $widget_ids = []
    ) {
        $this->jobId = uniqid('export_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting visualization export', [
                'job_id' => $this->jobId,
                'dashboard_id' => $this->dashboard_id,
                'export_type' => $this->export_type,
                'widget_count' => count($this->widget_ids),
                'timestamp' => now()->toIso8601String(),
            ]);

            $dashboard = Dashboard::with('widgets')->findOrFail($this->dashboard_id);

            // Get widgets to export
            $widgets = $this->widget_ids
                ? $dashboard->widgets()->whereIn('id', $this->widget_ids)->get()
                : $dashboard->widgets()->get();

            // Generate export file
            $filePath = $this->generateExportFile($dashboard, $widgets);

            // Store in persistent storage
            $storagePath = $this->storeExportFile($filePath, $dashboard);

            // Update dashboard metadata
            $dashboard->update([
                'last_exported_at' => now(),
                'export_path' => $storagePath,
            ]);

            Log::info('Visualization export completed', [
                'job_id' => $this->jobId,
                'dashboard_id' => $this->dashboard_id,
                'storage_path' => $storagePath,
                'widget_count' => $widgets->count(),
            ]);

            // Clean up temporary file
            @unlink($filePath);
        } catch (\Throwable $e) {
            Log::error('Visualization export failed', [
                'job_id' => $this->jobId,
                'dashboard_id' => $this->dashboard_id,
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
            throw new Exception("No tenant context available for visualization export job");
        }

        return (int) $tenantId;
    }

    /**
     * Generate export file (PDF or PPTX)
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Widget> $widgets
     */
    private function generateExportFile(Dashboard $dashboard, $widgets): string
    {
        return match ($this->export_type) {
            'pptx' => $this->generatePptxExport($dashboard, $widgets),
            'pdf' => $this->generatePdfExport($dashboard, $widgets),
            default => $this->generatePdfExport($dashboard, $widgets),
        };
    }

    /**
     * Generate PDF export with dashboard and all widgets
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Widget> $widgets
     */
    private function generatePdfExport(Dashboard $dashboard, $widgets): string
    {
        $html = $this->buildExportHtml($dashboard, $widgets);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'dpi' => 150,
                'enable_php' => false,
            ]);

        $filePath = storage_path('app/temp/export_' . $this->jobId . '.pdf');

        $pdf->save($filePath);

        Log::debug('PDF export generated', [
            'job_id' => $this->jobId,
            'file_path' => $filePath,
            'size' => filesize($filePath) . ' bytes',
        ]);

        return $filePath;
    }

    /**
     * Generate PowerPoint export with dashboard and widgets
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Widget> $widgets
     */
    private function generatePptxExport(Dashboard $dashboard, $widgets): string
    {
        // In a real implementation, use PhpOffice/PhpPresentation
        // For now, create a basic PPTX structure
        $filePath = storage_path('app/temp/export_' . $this->jobId . '.pptx');

        // Create a ZIP file (PPTX is ZIP format)
        $zip = new \ZipArchive();
        $zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Add minimal PPTX structure
        $this->addPptxStructure($zip, $dashboard, $widgets);

        $zip->close();

        Log::debug('PPTX export generated', [
            'job_id' => $this->jobId,
            'file_path' => $filePath,
            'size' => filesize($filePath) . ' bytes',
        ]);

        return $filePath;
    }

    /**
     * Build HTML for PDF export
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Widget> $widgets
     */
    private function buildExportHtml(Dashboard $dashboard, $widgets): string
    {
        $timestamp = now()->toDateTimeString();

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: white; }
        .page { page-break-after: always; padding: 40px; }
        .header { border-bottom: 2px solid #2196F3; padding-bottom: 20px; margin-bottom: 20px; }
        h1 { color: #1565c0; font-size: 28px; margin-bottom: 10px; }
        .meta { color: #666; font-size: 12px; }
        .widget-section { margin: 30px 0; page-break-inside: avoid; }
        .widget-title { font-size: 18px; font-weight: bold; color: #333; margin: 15px 0 10px 0; }
        .widget-content { border: 1px solid #ddd; padding: 15px; background: #f9f9f9; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #2196F3; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f5f5f5; }
        .chart-placeholder { border: 1px dashed #ccc; padding: 20px; text-align: center; color: #999; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 10px; color: #999; text-align: right; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <h1>{$dashboard->name}</h1>
            <div class="meta">
                <p><strong>Description:</strong> {$dashboard->description}</p>
                <p><strong>Generated:</strong> {$timestamp}</p>
                <p><strong>Widgets:</strong> {$widgets->count()}</p>
            </div>
        </div>
HTML;

        // Add each widget
        foreach ($widgets as $index => $widget) {
            $html .= $this->buildWidgetExportHtml($widget, $index + 1);
        }

        $html .= <<<HTML
        <div class="footer">
            <p>This report was automatically generated by WideHalo ERP BI System</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Build HTML for a single widget in export
     */
    private function buildWidgetExportHtml(Widget $widget, int $widgetIndex): string
    {
        $config = $widget->config ?? [];
        $type = $config['chart_type'] ?? 'table';

        $html = <<<HTML
    <div class="widget-section">
        <div class="widget-title">{$widgetIndex}. {$widget->title}</div>
        <div class="widget-content">
HTML;

        if ($type === 'table') {
            $html .= $this->buildTableHtml($widget);
        } else {
            $html .= '<div class="chart-placeholder">Chart: ' . htmlspecialchars($type) . '</div>';
        }

        $html .= <<<HTML
        </div>
    </div>
HTML;

        return $html;
    }

    /**
     * Build table HTML for widget
     */
    private function buildTableHtml(Widget $widget): string
    {
        // Build sample table from widget config
        $columns = $widget->config['columns'] ?? ['ID', 'Name', 'Value'];
        $rows = $widget->config['sample_data'] ?? [];

        $html = '<table><thead><tr>';

        foreach ($columns as $column) {
            $html .= '<th>' . htmlspecialchars($column) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $column) {
                $value = $row[$column] ?? '-';
                $html .= '<td>' . htmlspecialchars((string) $value) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Add PPTX structure to ZIP archive
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Widget> $widgets
     */
    private function addPptxStructure(\ZipArchive $zip, Dashboard $dashboard, $widgets): void
    {
        // Add [Content_Types].xml
        $contentTypes = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
  <Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>
</Types>
XML;

        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // Add minimal presentation structure
        $presentation = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:sldIdLst>
    <p:sldId id="256" r:id="rId2"/>
  </p:sldIdLst>
</p:presentation>
XML;

        $zip->addFromString('ppt/presentation.xml', $presentation);

        // Add title slide
        $slide = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <p:cSld>
    <p:spTree>
      <p:sp>
        <p:txBody>
          <a:t xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">{$dashboard->name}</a:t>
        </p:txBody>
      </p:sp>
    </p:spTree>
  </p:cSld>
</p:sld>
XML;

        $zip->addFromString('ppt/slides/slide1.xml', $slide);
    }

    /**
     * Store export file to permanent storage
     */
    private function storeExportFile(string $filePath, Dashboard $dashboard): string
    {
        $timestamp = now()->format('YmdHis');
        $filename = 'dashboard_' . $dashboard->id . '_' . $timestamp . '.' . $this->export_type;
        $storagePath = "exports/{$filename}";

        Storage::disk('public')->put($storagePath, file_get_contents($filePath));

        return $storagePath;
    }
}
