<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Models\ReportSchedule;
use Modules\Reporting\Services\OhadaReportService;

class ReportingService
{
    public function __construct(
        private readonly OhadaReportService $ohada,
    ) {}

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

            $resultData = $this->resolveResultData($report, $params, $user);

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
     * Chantier 32.22: resolves what the report definition's execution
     * actually produces — either the real OHADA financial-report payload
     * (for the 7 system reports whose query_template is a literal SQL
     * comment, see OhadaReportService::runTemplate()'s own docblock for the
     * full story), or the raw query_template SQL (every other report,
     * system or user-authored). Wraps the OHADA payload as a single-element
     * array so `execute()`'s existing count()/result_data handling doesn't
     * need to special-case it.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, mixed>
     */
    private function resolveResultData(ReportDefinition $report, array $params, User $user): array
    {
        $tenantId = (int) ($user->company_id ?? 0);

        if ($report->is_system) {
            $ohadaPayload = $this->ohada->runTemplate($report->slug, $tenantId, $params);
            if ($ohadaPayload !== null) {
                return [$ohadaPayload];
            }
        }

        return $this->runQuery($report, $params, $tenantId);
    }

    /**
     * Run the parameterized query template safely.
     *
     * Supports {{param_name}} placeholders which are substituted as PDO
     * bindings. {{tenant_id}} is always auto-injected; a small set of other
     * common placeholders ({{today}}, {{as_of_date}}, {{period}}, {{year}},
     * {{month_start}}, {{month_end}}) auto-default to the current date/
     * period whenever the caller doesn't supply a value — this is what
     * every seeded ReportTemplateSeeder template with a `parameters_schema`
     * default actually needs: the real quick-report-tile callers
     * (ReportsIndex.vue) always POST an empty `parameters: {}` body, so a
     * declared schema default was previously pure documentation, never
     * applied — confirmed empirically that every seeded parameterized
     * report silently bound NULL for any placeholder the caller didn't
     * explicitly supply.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, mixed>
     */
    private function runQuery(ReportDefinition $report, array $params, int $tenantId): array
    {
        $template = $report->query_template;
        $auto     = self::autoParams($tenantId);

        $bindings = [];
        $template = preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($params, $auto, &$bindings): string {
            $key = $m[1];
            $bindings[] = $params[$key] ?? $auto[$key] ?? null;
            return '?';
        }, $template) ?? $template;

        return DB::select($template, $bindings);
    }

    /**
     * Auto-computed placeholder defaults, shared (via a plain static call —
     * no new dependency needed) by ReportGenerationService::executeQuery()
     * so both of this module's independent query-template executors apply
     * the exact same defaults rather than drifting apart.
     *
     * @return array<string, string>
     */
    public static function autoParams(int $tenantId): array
    {
        return [
            'tenant_id'   => (string) $tenantId,
            'today'       => now()->toDateString(),
            'as_of_date'  => now()->toDateString(),
            'period'      => now()->format('Y-m'),
            'year'        => (string) now()->year,
            'month_start' => now()->startOfMonth()->toDateString(),
            'month_end'   => now()->copy()->startOfMonth()->addMonthNoOverflow()->toDateString(),
        ];
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
