<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Services\OhadaReportService;
use Modules\Reporting\Services\ReportingService;

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

    public function __construct(
        private readonly OhadaReportService $ohada,
    ) {}

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

        // Chantier 32.22: `$report->tenant_id ?? 1` / `auth()->id() ?? 1` —
        // the well-documented phantom-tenant-1 fallback pattern already
        // fixed repeatedly elsewhere in this app (see
        // ReportingController::tenantId()'s own docblock), just never
        // reached here because this method had zero real caller until this
        // chantier wired scheduled delivery to it. `auth()->id()` is also
        // structurally never populated in a queued-job context anyway
        // (there is no authenticated request) — `executed_by` is a real
        // nullable column, so null (a system-triggered run, honestly
        // represented) replaces the meaningless hardcoded user id 1.
        $execution ??= ReportExecution::create([
            'tenant_id'            => $report->tenant_id ?? 0,
            'report_definition_id' => $report->id,
            'executed_by'          => auth()->id(),
            'triggered_by'         => 'api',
            'parameters'           => $params,
            'output_format'        => $format,
            'status'               => 'running',
            'started_at'           => now(),
        ]);

        try {
            $startMs = (int) (microtime(true) * 1000);

            $data = $this->resolveData($report, $params, $execution->tenant_id);

            // Chantier 32.22: was `'row_count'` — not a real column on
            // report_executions at all (confirmed via
            // Schema::getColumnListing(): the table has both a legacy
            // `rows_count` scaffold column and the real, model-`$fillable`
            // `result_count` one this module's actively-used execution path
            // (ReportingService::execute()) already writes — `row_count`
            // (singular, no 's') matched neither, so this write was
            // silently dropped by Eloquent's mass-assignment guard on every
            // call. Aligned onto the same real column the rest of the
            // module already uses consistently.
            $execution->update([
                'status'    => 'completed',
                'result_count' => count($data),
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
                // Chantier 32.22: `output_url` was never a real column on
                // report_executions (confirmed via
                // Schema::getColumnListing()) — silently dropped, dead
                // weight, no consumer anywhere reads it (Show.vue/
                // ReportsIndex.vue's downloads go through the real
                // GET .../executions/{id}/download endpoint, never a
                // stored URL). Dropped rather than resurrected as a phantom
                // column write.
                $execution->update(['file_path' => $filePath]);
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
                    $sheet->fromArray([array_map([$this, 'scalarize'], array_values((array) $row))], null, "A{$rowIdx}");
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
     * Chantier 32.22: resolves what run() actually delivers — the real OHADA
     * financial-report payload for the 7 system reports whose
     * query_template is a literal SQL comment (see OhadaReportService::
     * runTemplate()'s docblock), or the raw query_template SQL otherwise.
     * Wrapped as a single-element array so the row_count/preview/export
     * plumbing in run() doesn't need to special-case it — mirrors
     * ReportingService::resolveResultData()'s identical decision on the
     * other, previously-only, executor.
     *
     * @return array<int, mixed>
     */
    private function resolveData(ReportDefinition $report, array $params, int $tenantId): array
    {
        if ($report->is_system) {
            $ohadaPayload = $this->ohada->runTemplate($report->slug, $tenantId, $params);
            if ($ohadaPayload !== null) {
                return [$ohadaPayload];
            }
        }

        return $this->executeQuery($report, $params, $tenantId);
    }

    /**
     * Executes the report's query template with tenant isolation.
     *
     * Chantier 32.22: `{{tenant_id}}` used to be substituted unconditionally
     * — `$bindings = [$tenantId]` was always seeded with one entry even when
     * the template never actually contained a `{{tenant_id}}` placeholder,
     * so any template with one or more *other* `{{param}}` placeholders
     * (e.g. every seeded ReportTemplateSeeder row that also needs
     * `{{period}}`/`{{month}}`) ended up with more bound values than `?`
     * marks — a guaranteed PDO "number of bound variables does not match"
     * error on first real use, confirmed via the same repo-wide-unused-until-
     * this-chantier status as everything else in this method. Rewritten
     * onto ReportingService::autoParams()'s single unified substitution
     * pass, which only binds a value for a placeholder that's actually
     * present in the template — this method's own bespoke duplicate is
     * deleted, not kept as a second slightly-different implementation.
     * Also: `' LIMIT ' . self::MAX_ROWS` used to be appended
     * unconditionally, even onto a template that already ends in its own
     * `LIMIT n` (as several seeded templates do) — `... LIMIT 500 LIMIT
     * 50000` is invalid SQL on both this app's real drivers (SQLite and
     * MySQL each allow only one LIMIT clause) — now only appended when the
     * template doesn't already declare one.
     *
     * @return array<int, mixed>
     */
    private function executeQuery(ReportDefinition $report, array $params, int $tenantId): array
    {
        $template = $report->query_template ?? 'SELECT 1 as result';
        $auto     = ReportingService::autoParams($tenantId);

        $bindings = [];
        $template = preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($params, $auto, &$bindings): string {
            $key = $m[1];
            $bindings[] = $params[$key] ?? $auto[$key] ?? null;
            return '?';
        }, $template) ?? $template;

        if (! preg_match('/\bLIMIT\s+\d+\s*$/i', trim($template))) {
            $template .= ' LIMIT ' . self::MAX_ROWS;
        }

        $results = DB::select($template, $bindings);

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

    /**
     * Chantier 32.26: OHADA report payloads carry nested arrays (actif/
     * passif rubriques, aged-balance buckets) — every generic export path
     * below used a bare `(string) $value` cast, an "Array to string
     * conversion" error the first time a real OHADA execution was ever
     * exported (found by actually running DeliverScheduledReportJob end to
     * end). Non-scalar cells are JSON-encoded rather than dropped, so the
     * exported file stays faithful to the real data.
     */
    private function scalarize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return is_scalar($value)
            ? (string) $value
            : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
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
        $str = $this->scalarize($value);
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
                $cells     = implode('', array_map(fn ($v) => '<td>' . htmlspecialchars($this->scalarize($v)) . '</td>', (array) $row));
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
