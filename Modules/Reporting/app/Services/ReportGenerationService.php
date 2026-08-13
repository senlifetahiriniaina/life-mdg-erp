<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;

/**
 * ReportGenerationService
 *
 * Executes report definitions, exports results to PDF/XLSX/CSV,
 * schedules recurring delivery and dispatches email notifications.
 *
 * Export engines:
 *  - PDF:  HTML template rendered to PDF (via Dompdf if available, otherwise HTML file)
 *  - XLSX: CSV-compatible spreadsheet (via PhpSpreadsheet if available, otherwise CSV)
 *  - CSV:  RFC 4180 compliant, UTF-8 BOM for Excel compatibility
 */
class ReportGenerationService
{
    private const MAX_ROWS = 50_000;

    // ─── Core execution ────────────────────────────────────────────────────────

    /**
     * Runs a report definition with given parameters and format.
     * Populates an existing ReportExecution record (or creates one if $execution is null).
     */
    public function run(
        ReportDefinition $report,
        array            $params = [],
        string           $format = 'json',
        ?ReportExecution $execution = null,
    ): ReportExecution {

        $execution ??= ReportExecution::create([
            'tenant_id'            => $report->tenant_id ?? 1,
            'report_definition_id' => $report->id,
            'executed_by'          => auth()->id() ?? 1,
            'triggered_by'         => 'api',
            'parameters'           => $params,
            'output_format'        => $format,
            'status'               => 'running',
            'started_at'           => now(),
        ]);

        try {
            $startMs = (int) (microtime(true) * 1000);

            $data = $this->executeQuery($report, $params, $execution->tenant_id);

            $execution->update([
                'status'    => 'completed',
                'row_count' => count($data),
                'result_data' => array_slice($data, 0, 5),  // store preview in DB
                'duration_ms' => (int) (microtime(true) * 1000) - $startMs,
                'completed_at' => now(),
            ]);

            // Generate file if export format requested
            if (in_array($format, ['pdf', 'xlsx', 'csv'], true)) {
                $filePath = match ($format) {
                    'pdf'  => $this->exportPdf($execution, $data),
                    'xlsx' => $this->exportXlsx($execution, $data),
                    'csv'  => $this->exportCsv($execution, $data),
                };
                $execution->update([
                    'file_path'  => $filePath,
                    'output_url' => Storage::url($filePath),
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('ReportGenerationService::run failed', [
                'report_id'    => $report->id,
                'execution_id' => $execution->id,
                'error'        => $e->getMessage(),
            ]);
            $execution->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }

        return $execution->fresh();
    }

    // ─── Export: PDF ──────────────────────────────────────────────────────────

    /**
     * Exports a completed execution to PDF.
     * Returns the storage path of the generated file.
     */
    public function exportPdf(ReportExecution $execution, array $data = []): string
    {
        $report   = $execution->definition;
        $filename = $this->buildFilePath($execution->id, 'pdf');
        $html     = $this->buildHtmlReport($execution, $report, $data);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            Storage::put($filename, $pdf->output());
        } else {
            // Fallback: store as HTML if DomPDF is not installed
            $htmlFilename = str_replace('.pdf', '.html', $filename);
            Storage::put($htmlFilename, $html);
            Storage::put($filename, $html);  // store under .pdf name too
        }

        return $filename;
    }

    // ─── Export: XLSX/CSV ─────────────────────────────────────────────────────

    /**
     * Exports a completed execution to XLSX (CSV-compatible).
     * Returns the storage path of the generated file.
     */
    public function exportXlsx(ReportExecution $execution, array $data = []): string
    {
        $filename = $this->buildFilePath($execution->id, 'xlsx');

        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();

            if (! empty($data)) {
                $headers = array_keys((array) $data[0]);
                $sheet->fromArray([$headers], null, 'A1');
                $rowIdx = 2;
                foreach ($data as $row) {
                    $sheet->fromArray([array_values((array) $row)], null, "A{$rowIdx}");
                    $rowIdx++;
                }
            }

            $writer  = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $tmpPath = tempnam(sys_get_temp_dir(), 'report_');
            $writer->save($tmpPath);
            Storage::put($filename, file_get_contents($tmpPath));
            @unlink($tmpPath);

        } else {
            // Fallback: CSV with BOM for Excel UTF-8 compatibility
            $csv      = "\xEF\xBB\xBF";  // UTF-8 BOM
            $csvFile  = $this->buildFilePath($execution->id, 'csv');
            $csv     .= $this->buildCsvContent($data);
            Storage::put($csvFile, $csv);
            Storage::put($filename, $csv);
        }

        return $filename;
    }

    /**
     * Exports a completed execution to RFC 4180 CSV (UTF-8 BOM for Excel).
     * Returns the storage path of the generated file.
     */
    public function exportCsv(ReportExecution $execution, array $data = []): string
    {
        $filename = $this->buildFilePath($execution->id, 'csv');
        $csv      = "\xEF\xBB\xBF" . $this->buildCsvContent($data);  // UTF-8 BOM
        Storage::put($filename, $csv);
        return $filename;
    }

    // ─── Scheduling ───────────────────────────────────────────────────────────

    /**
     * Saves a schedule configuration on a ReportDefinition.
     * The actual dispatch is done by DeliverScheduledReportJob via the scheduler.
     *
     * @param array{
     *   frequency: 'weekly'|'monthly'|'quarterly',
     *   day: int,
     *   time: string,
     *   recipients: string[],
     *   format: string
     * } $scheduleConfig
     */
    public function schedule(ReportDefinition $report, array $scheduleConfig): void
    {
        $report->update([
            'schedule'  => $scheduleConfig,
            'is_active' => true,
        ]);
    }

    // ─── Email delivery ───────────────────────────────────────────────────────

    /**
     * Sends a completed report execution by email to given recipients.
     *
     * @param string[] $recipients
     */
    public function deliverByEmail(ReportExecution $execution, array $recipients): void
    {
        if (empty($recipients)) {
            return;
        }

        $report      = $execution->definition;
        $reportName  = $report?->name ?? 'Rapport WideHalo';
        $filePath    = $execution->file_path;
        $format      = $execution->output_format ?? 'pdf';

        try {
            foreach ($recipients as $email) {
                Mail::raw(
                    "Bonjour,\n\nVeuillez trouver ci-joint le rapport : {$reportName}.\n"
                    . "Période : " . now()->format('d/m/Y') . "\n\n"
                    . "Ce rapport a été généré automatiquement par WideHalo ERP.\n\n"
                    . "Cordialement,\nL'équipe WideHalo",
                    function ($message) use ($email, $reportName, $filePath, $format) {
                        $message->to($email)
                            ->subject("[WideHalo] {$reportName} – " . now()->format('d/m/Y'));

                        if ($filePath && Storage::exists($filePath)) {
                            $mimeType = match ($format) {
                                'pdf'  => 'application/pdf',
                                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'csv'  => 'text/csv; charset=UTF-8',
                                default => 'application/octet-stream',
                            };
                            $message->attachData(
                                Storage::get($filePath),
                                basename($filePath),
                                ['mime' => $mimeType],
                            );
                        }
                    }
                );
            }

            Log::info('ReportGenerationService: report delivered by email', [
                'execution_id' => $execution->id,
                'recipients'   => $recipients,
            ]);

        } catch (\Exception $e) {
            Log::error('ReportGenerationService: email delivery failed', [
                'execution_id' => $execution->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Executes the report's query template with tenant isolation.
     *
     * @return array<int, mixed>
     */
    private function executeQuery(ReportDefinition $report, array $params, int $tenantId): array
    {
        $template = $report->query_template ?? 'SELECT 1 as result';

        // Always inject tenant_id for multi-tenant safety
        $template = str_replace('{{tenant_id}}', '?', $template);
        $bindings = [$tenantId];

        // Replace remaining {{param}} placeholders
        $template = preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($params, &$bindings): string {
            $bindings[] = $params[$m[1]] ?? null;
            return '?';
        }, $template) ?? $template;

        $results = DB::select($template . ' LIMIT ' . self::MAX_ROWS, $bindings);

        return array_map(fn ($row) => (array) $row, $results);
    }

    private function buildFilePath(int $executionId, string $extension): string
    {
        return sprintf(
            'reports/%s/execution_%d_%s.%s',
            $extension,
            $executionId,
            now()->format('Ymd_His'),
            $extension,
        );
    }

    private function buildCsvContent(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $csv     = '';
        $headers = array_keys((array) $data[0]);
        $csv    .= implode(',', array_map([$this, 'csvEscape'], $headers)) . "\r\n";

        foreach ($data as $row) {
            $csv .= implode(',', array_map([$this, 'csvEscape'], array_values((array) $row))) . "\r\n";
        }

        return $csv;
    }

    private function csvEscape(mixed $value): string
    {
        $str = (string) ($value ?? '');
        if (str_contains($str, ',') || str_contains($str, '"') || str_contains($str, "\n")) {
            return '"' . str_replace('"', '""', $str) . '"';
        }
        return $str;
    }

    /**
     * Builds an HTML report template for PDF export.
     * Includes WideHalo branding and OHADA indicator when report is financial.
     */
    private function buildHtmlReport(ReportExecution $execution, ?ReportDefinition $report, array $data): string
    {
        $title      = $report?->name ?? 'Rapport';
        $isOhada    = str_contains(strtolower($report?->name ?? ''), 'ohada')
                   || str_contains(strtolower($report?->module ?? ''), 'accounting');
        $ohadaBadge = $isOhada ? '<span style="background:#2e7d32;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">OHADA/SYSCOHADA</span>' : '';
        $dateStr    = now()->format('d/m/Y H:i');
        $rowCount   = number_format(count($data));

        // Table headers & rows
        $tableHtml = '';
        if (! empty($data)) {
            $headers    = array_keys((array) $data[0]);
            $headerHtml = implode('', array_map(fn ($h) => "<th>{$h}</th>", $headers));
            $bodyRows   = '';
            foreach (array_slice($data, 0, 5000) as $i => $row) {
                $rowClass  = $i % 2 === 0 ? 'even' : 'odd';
                $cells     = implode('', array_map(fn ($v) => '<td>' . htmlspecialchars((string) ($v ?? '')) . '</td>', (array) $row));
                $bodyRows .= "<tr class=\"{$rowClass}\">{$cells}</tr>";
            }
            $tableHtml = "<table><thead><tr>{$headerHtml}</tr></thead><tbody>{$bodyRows}</tbody></table>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
  .header { border-bottom: 2px solid #1565c0; padding-bottom: 10px; margin-bottom: 15px; }
  .header h1 { font-size: 18px; color: #1565c0; margin: 0; }
  .header .meta { font-size: 10px; color: #666; margin-top: 4px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th { background: #1565c0; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
  td { padding: 4px 8px; border-bottom: 1px solid #eee; font-size: 11px; }
  tr.odd { background: #f9f9f9; }
  .footer { margin-top: 20px; font-size: 9px; color: #999; text-align: center; }
</style>
</head>
<body>
  <div class="header">
    <h1>WideHalo ERP — {$title} {$ohadaBadge}</h1>
    <div class="meta">Généré le {$dateStr} · {$rowCount} ligne(s) · Exécution #{$execution->id}</div>
  </div>
  {$tableHtml}
  <div class="footer">Ce rapport est confidentiel. Généré par WideHalo ERP · ©2026 WideHalo</div>
</body>
</html>
HTML;
    }
}
