<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Models\ReportSchedule;

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
            // Chantier 8 (Reporting): was $user->tenant_id ?? 1 — users.tenant_id
            // is never populated in practice (see ReportingController::tenantId()'s
            // docblock), so every execution was silently attributed to tenant 1.
            // company_id is the real multi-tenant boundary column.
            'tenant_id'            => $user->company_id ?? 0,
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
     * Create a recurring schedule for a report definition.
     *
     * @param  array<string, mixed>  $config  frequency, cron_expression, recipients, is_active, name (extra keys ignored)
     */
    public function schedule(ReportDefinition $report, array $config): ReportSchedule
    {
        return ReportSchedule::create([
            'tenant_id'            => $report->tenant_id ?? ($config['tenant_id'] ?? 0),
            'report_definition_id' => $report->id,
            'name'                 => $config['name'] ?? $report->name,
            'frequency'            => $config['frequency'] ?? 'daily',
            'cron_expression'      => $config['cron_expression'] ?? null,
            'recipients'           => $config['recipients'] ?? [],
            'is_active'            => $config['is_active'] ?? true,
        ]);
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

        // Always inject tenant_id automatically for multi-tenant safety.
        // Chantier 8 (Reporting): was $user->tenant_id ?? 1 — see
        // ReportingController::tenantId()'s docblock.
        $tenantId = $user->company_id ?? 0;

        // Replace {{tenant_id}} placeholder
        $template = str_replace('{{tenant_id}}', '?', $template, $count);
        $bindings = $count > 0 ? [$tenantId] : [];

        // Replace remaining {{param_name}} placeholders from provided params
        $template = preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($params, &$bindings): string {
            $key = $m[1];
            $bindings[] = $params[$key] ?? null;
            return '?';
        }, $template) ?? $template;

        return DB::select($template, $bindings);
    }

    // Chantier 29: generatePdf()/generateExcel()/buildTextReport() were
    // deleted here — both were fakes (plain-text saved under a .pdf name;
    // CSV saved under a .csv name regardless of the requested format's real
    // MIME type), confirmed via grep to have exactly one caller in the
    // whole app (ReportingController::downloadExecution()), which now
    // delegates to the real DomPDF/PhpSpreadsheet engine on
    // ReportGenerationService::exportPdf()/exportXlsx() instead. See that
    // controller method's own docblock for the full story.
}
