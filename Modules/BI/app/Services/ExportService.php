<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Widget;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /** @param Collection<int, mixed> $data */
    public function toXlsx(Collection $data, array $headings, string $filename): BinaryFileResponse
    {
        $export = new class($data, $headings) implements FromCollection, WithHeadings, WithStyles
        {
            /** @param Collection<int, mixed> $rows */
            public function __construct(
                private readonly Collection $rows,
                private readonly array $cols
            ) {}

            public function collection(): Collection
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->cols;
            }

            public function styles(Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true]]];
            }
        };

        return Excel::download($export, $filename.'.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    /** @param Collection<int, mixed> $data */
    public function toCsv(Collection $data, array $headings, string $filename): BinaryFileResponse
    {
        $export = new class($data, $headings) implements FromCollection, WithHeadings
        {
            /** @param Collection<int, mixed> $rows */
            public function __construct(
                private readonly Collection $rows,
                private readonly array $cols
            ) {}

            public function collection(): Collection
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->cols;
            }
        };

        return Excel::download($export, $filename.'.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function toPdf(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'sans-serif']);

        return $pdf->stream($filename.'.pdf');
    }

    /**
     * Export a widget's data as a streamed CSV download.
     *
     * Chantier 29: previously wrote only a header row and closed the stream —
     * a widget CSV export always downloaded an empty body. No generic
     * "resolve this widget's query/data source into real rows" mechanism
     * exists anywhere in this module (`DrillDownService::getWidgetBaseData()`
     * is itself an unfinished stub that always returns `[]`), so this method
     * prefers real per-row data when the widget's own `config` already
     * carries it (e.g. a `data_table` widget the builder saved with a
     * `rows` array alongside its `columns`), and otherwise falls back to a
     * genuine data row built from the widget's own real, stored attributes
     * — never an invented value.
     */
    public function exportWidgetCsv(Widget $widget): StreamedResponse
    {
        /** @var array<string, mixed> $config */
        $config = $widget->config ?? [];
        $filename = 'widget_'.$widget->id.'_'.now()->format('Ymd_His').'.csv';

        /** @var list<array<string, mixed>>|null $configRows */
        $configRows = isset($config['rows']) && is_array($config['rows']) && $config['rows'] !== []
            ? array_values($config['rows'])
            : null;

        $summary = [
            'id' => $widget->id,
            'title' => $widget->title,
            'type' => $widget->type,
            'dashboard' => $widget->dashboard?->name,
            'refresh_interval' => $widget->refresh_interval,
            'created_at' => $widget->created_at?->toDateTimeString(),
        ];

        return response()->streamDownload(function () use ($config, $configRows, $summary): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            if ($configRows !== null) {
                $firstRow = $configRows[0];
                $headers = isset($config['columns']) && is_array($config['columns'])
                    ? $config['columns']
                    : array_keys($firstRow);

                fputcsv($out, $headers);

                foreach ($configRows as $row) {
                    fputcsv($out, array_map(
                        fn (string $column) => $row[$column] ?? '',
                        $headers
                    ));
                }
            } else {
                $headers = isset($config['columns']) && is_array($config['columns'])
                    ? $config['columns']
                    : array_keys($summary);

                fputcsv($out, $headers);
                fputcsv($out, array_map(
                    fn (string $column) => $summary[$column] ?? ($config[$column] ?? ''),
                    $headers
                ));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Export a widget as PDF (HTML rendered via DomPDF).
     */
    public function exportWidgetPdf(Widget $widget): Response
    {
        $html = $this->buildWidgetHtml($widget);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'sans-serif']);

        return $pdf->stream('widget_'.$widget->id.'.pdf');
    }

    /**
     * Export a full dashboard as PDF.
     */
    public function exportDashboardPdf(Dashboard $dashboard): Response
    {
        $html = $this->buildDashboardHtml($dashboard);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'sans-serif']);

        return $pdf->stream('dashboard_'.$dashboard->id.'.pdf');
    }

    /**
     * Export a raw query result array as a streamed CSV download.
     *
     * @param  array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}  $result
     */
    public function exportQueryCsv(array $result, string $filename): StreamedResponse
    {
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename).'.csv';

        return response()->streamDownload(function () use ($result): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $result['columns']);
            foreach ($result['rows'] as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, $safeFilename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Export a raw query result as XLSX (falls back to CSV with .xlsx extension if PhpSpreadsheet unavailable).
     *
     * @param  array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}  $result
     */
    public function exportQueryXlsx(array $result, string $filename): StreamedResponse|BinaryFileResponse
    {
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
        $rows = Collection::make(
            array_map(fn (array $r): array => array_values($r), $result['rows'])
        );

        return $this->toXlsx($rows, $result['columns'], $safeFilename);
    }

    private function buildWidgetHtml(Widget $widget): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>body{font-family:sans-serif;padding:20px;}h1{font-size:18px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;}th{background:#f5f5f5;font-weight:600;}</style>
</head>
<body>
<h1>{$widget->title}</h1>
<p>Type: {$widget->type} | Generated: {$this->safeDate()}</p>
</body>
</html>
HTML;
    }

    private function buildDashboardHtml(Dashboard $dashboard): string
    {
        $widgets = $dashboard->widgets()->get();
        $widgetRows = '';
        foreach ($widgets as $w) {
            $widgetRows .= "<tr><td>{$w->id}</td><td>{$w->title}</td><td>{$w->type}</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>body{font-family:sans-serif;padding:20px;}h1{font-size:20px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;}th{background:#f5f5f5;font-weight:600;}</style>
</head>
<body>
<h1>{$dashboard->name}</h1>
<p>Generated: {$this->safeDate()}</p>
<table>
<thead><tr><th>#</th><th>Widget</th><th>Type</th></tr></thead>
<tbody>{$widgetRows}</tbody>
</table>
</body>
</html>
HTML;
    }

    private function safeDate(): string
    {
        return now()->toDateTimeString();
    }
}
