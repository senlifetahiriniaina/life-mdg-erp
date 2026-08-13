<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;

class ReportingService
{
    /**
     * Get available report definitions visible to the current tenant.
     * Pass a module name to filter by module.
     */
    public function getAvailableReports(?string $module = null): Collection
    {
        return ReportDefinition::active()
            ->when($module !== null, fn ($q) => $q->forModule($module))
            ->orderBy('module')
            ->orderBy('name')
            ->get();
    }

    /**
     * Execute a report definition synchronously and persist the result.
     *
     * @param  array<string, mixed>  $params
     */
    public function execute(ReportDefinition $report, array $params, User $user): ReportExecution
    {
        $execution = ReportExecution::create([
            'tenant_id'            => $user->tenant_id ?? 1,
            'report_definition_id' => $report->id,
            'executed_by'          => $user->id,
            'parameters'           => $params,
            'status'               => 'pending',
            'started_at'           => null,
        ]);

        try {
            $execution->update(['status' => 'running', 'started_at' => now()]);

            $resultData = $this->runQuery($report, $params, $user);

            $execution->update([
                'status'       => 'completed',
                'result_count' => count($resultData),
                'result_data'  => $resultData,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $execution->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }

        return $execution->fresh();
    }

    /**
     * Run the parameterized query template safely.
     *
     * Supports {{param_name}} placeholders which are substituted as PDO bindings.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, mixed>
     */
    private function runQuery(ReportDefinition $report, array $params, User $user): array
    {
        $template = $report->query_template;

        // Always inject tenant_id automatically for multi-tenant safety
        $tenantId = $user->tenant_id ?? 1;

        // Replace {{tenant_id}} placeholder
        $template = str_replace('{{tenant_id}}', '?', $template);
        $bindings = [$tenantId];

        // Replace remaining {{param_name}} placeholders from provided params
        $template = preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($params, &$bindings): string {
            $key = $m[1];
            $bindings[] = $params[$key] ?? null;
            return '?';
        }, $template) ?? $template;

        return DB::select($template, $bindings);
    }

    /**
     * Generate a PDF export for a completed execution.
     * Returns the storage path of the generated file.
     */
    public function generatePdf(ReportExecution $execution): string
    {
        if (! $execution->isCompleted()) {
            throw new \RuntimeException('Cannot generate PDF for an execution that is not completed.');
        }

        $filename = sprintf(
            'reports/pdf/execution_%d_%s.pdf',
            $execution->id,
            now()->format('Ymd_His'),
        );

        // Build a simple CSV-like text payload as a PDF stand-in
        // (full PDF generation requires a library like DomPDF/Snappy — plug in here)
        $content = $this->buildTextReport($execution);
        Storage::put($filename, $content);

        $execution->update(['file_path' => $filename]);

        return $filename;
    }

    /**
     * Generate an Excel export for a completed execution.
     * Returns the storage path of the generated file.
     */
    public function generateExcel(ReportExecution $execution): string
    {
        if (! $execution->isCompleted()) {
            throw new \RuntimeException('Cannot generate Excel for an execution that is not completed.');
        }

        $filename = sprintf(
            'reports/excel/execution_%d_%s.csv',
            $execution->id,
            now()->format('Ymd_His'),
        );

        $rows = $execution->result_data ?? [];
        $csv  = '';

        if (! empty($rows)) {
            $headers = array_keys((array) $rows[0]);
            $csv     = implode(',', $headers) . "\n";

            foreach ($rows as $row) {
                $values = array_map(
                    fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                    array_values((array) $row),
                );
                $csv .= implode(',', $values) . "\n";
            }
        }

        Storage::put($filename, $csv);

        $execution->update(['file_path' => $filename]);

        return $filename;
    }

    /**
     * Build a plain-text representation of the execution result for quick export.
     */
    private function buildTextReport(ReportExecution $execution): string
    {
        $rows  = $execution->result_data ?? [];
        $lines = ["Report Execution #{$execution->id}", str_repeat('-', 40)];

        foreach ($rows as $row) {
            foreach ((array) $row as $key => $value) {
                $lines[] = "{$key}: {$value}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
