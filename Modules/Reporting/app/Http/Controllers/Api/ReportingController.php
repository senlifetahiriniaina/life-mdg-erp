<?php

declare(strict_types=1);

namespace Modules\Reporting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Reporting\Jobs\RunReportJob;
use Modules\Reporting\Models\Dashboard;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Models\ReportSchedule;
use Modules\Reporting\Models\ReportShare;
use Modules\Reporting\Models\ReportWidget;
use Modules\Reporting\Models\SavedQuery;
use Modules\Reporting\Services\DashboardService;
use Modules\Reporting\Services\NlToSqlService;
use Modules\Reporting\Services\OhadaReportService;
use Modules\Reporting\Services\ReportGenerationService;
use Modules\Reporting\Services\ReportingService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Reporting
 *
 * Cross-module report generation, execution, scheduling, sharing and export.
 */
class ReportingController extends Controller
{
    public function __construct(
        private readonly ReportingService        $service,
        private readonly OhadaReportService      $ohada,
        private readonly NlToSqlService          $nlSql,
        private readonly ReportGenerationService $generation,
        private readonly DashboardService        $dashboards,
    ) {}

    // ─── Report Definitions (CRUD) ─────────────────────────────────────────────

    /**
     * List available report definitions for the authenticated tenant.
     *
     * @queryParam module string Filter by module name. Example: Sales
     * @queryParam report_type string Filter by type (table|chart|pivot|export). Example: chart
     * @queryParam per_page integer Results per page (max 100). Example: 25
     */
    public function listReports(Request $request): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $module    = $request->get('module');
        $type      = $request->get('report_type');
        $perPage   = min((int) ($request->get('per_page', 25)), 100);

        $reports = ReportDefinition::active()
            ->visibleTo($tenantId)
            ->when($module !== null, fn ($q) => $q->forModule($module))
            ->when($type !== null, fn ($q) => $q->forReportType($type))
            ->orderBy('module')
            ->orderBy('name')
            ->paginate($perPage, [
                'id', 'name', 'slug', 'module', 'description',
                'output_format', 'report_type', 'is_system', 'parameters_schema',
            ]);

        return response()->json($reports);
    }

    /**
     * Create a report definition.
     *
     * @bodyParam name string required Report name. Example: Monthly Sales Summary
     * @bodyParam module string required Source module. Example: Sales
     * @bodyParam description string Optional description. Example: Month-by-month breakdown
     * @bodyParam query_template string required Parameterized SQL template. Example: SELECT * FROM sales WHERE tenant_id = {{tenant_id}}
     * @bodyParam parameters_schema object JSON schema for parameters. Example: {}
     * @bodyParam output_format string One of: table, chart, kpi, pdf, excel. Default: table
     * @bodyParam report_type string One of: table, chart, pivot, export. Default: table
     */
    public function storeReport(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'name'              => 'required|string|max:100',
            'module'            => 'required|string|max:50',
            'description'       => 'nullable|string|max:500',
            'query_template'    => 'required|string',
            'parameters_schema' => 'nullable|array',
            'output_format'     => 'nullable|in:table,chart,kpi,pdf,excel',
            'report_type'       => 'nullable|in:table,chart,pivot,export',
        ]);

        $report = ReportDefinition::create([
            'tenant_id'         => $tenantId,
            'name'              => $validated['name'],
            'slug'              => Str::slug($validated['name']) . '-' . Str::random(6),
            'module'            => $validated['module'],
            'description'       => $validated['description'] ?? null,
            'query_template'    => $validated['query_template'],
            'parameters_schema' => $validated['parameters_schema'] ?? null,
            'output_format'     => $validated['output_format'] ?? 'table',
            'report_type'       => $validated['report_type'] ?? 'table',
            'is_system'         => false,
            'is_active'         => true,
            'created_by'        => $request->user()->id,
        ]);

        return response()->json($report, 201);
    }

    /**
     * Show a single report definition.
     *
     * @urlParam id integer required The report ID. Example: 1
     */
    public function showReport(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $report = ReportDefinition::visibleTo($tenantId)->findOrFail($id);

        return response()->json($report->load('createdBy:id,name,email'));
    }

    /**
     * Update a report definition.
     *
     * @urlParam id integer required The report ID. Example: 1
     */
    public function updateReport(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $report = ReportDefinition::forTenant($tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name'              => 'sometimes|string|max:100',
            'module'            => 'sometimes|string|max:50',
            'description'       => 'nullable|string|max:500',
            'query_template'    => 'sometimes|string',
            'parameters_schema' => 'nullable|array',
            'output_format'     => 'nullable|in:table,chart,kpi,pdf,excel',
            'report_type'       => 'nullable|in:table,chart,pivot,export',
            'is_active'         => 'sometimes|boolean',
        ]);

        $report->update($validated);

        return response()->json($report->fresh());
    }

    /**
     * Delete a report definition (tenant-owned only).
     *
     * @urlParam id integer required The report ID. Example: 1
     */
    public function destroyReport(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $report = ReportDefinition::forTenant($tenantId)->findOrFail($id);

        if ($report->is_system) {
            return response()->json(['message' => 'Cannot delete a system report.'], 422);
        }

        $report->delete();

        return response()->json(['message' => 'Report deleted.'], 200);
    }

    // ─── Execute / Run ─────────────────────────────────────────────────────────

    /**
     * Execute a report by its slug.
     *
     * @urlParam slug string required The report slug. Example: sales-monthly-summary
     * @bodyParam parameters object Parameters required by the report's schema. Example: {"year":2026}
     */
    public function executeReport(Request $request, string $slug): JsonResponse
    {
        $report = ReportDefinition::where('slug', $slug)->active()->firstOrFail();

        $tenantId = $this->tenantId($request);
        if ($report->tenant_id !== null && $report->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $params    = $request->input('parameters', []);
        $execution = $this->service->execute($report, $params, $request->user());

        return response()->json($execution->load('definition:id,name,slug,output_format'), 201);
    }

    /**
     * Run a report by its ID (creates a new ReportExecution).
     *
     * @urlParam id integer required The report definition ID. Example: 1
     * @bodyParam parameters object Optional parameters. Example: {"year":2026}
     */
    public function runReport(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $report = ReportDefinition::active()
            ->visibleTo($tenantId)
            ->findOrFail($id);

        $params    = $request->input('parameters', []);
        $execution = $this->service->execute($report, $params, $request->user());

        return response()->json([
            'execution_id' => $execution->id,
            'status'       => $execution->status,
            'execution'    => $execution->load('definition:id,name,slug,output_format'),
        ], 201);
    }

    // ─── Executions ────────────────────────────────────────────────────────────

    /**
     * List all executions for a specific report.
     *
     * @urlParam id integer required The report definition ID. Example: 1
     * @queryParam per_page integer Results per page (max 100). Example: 25
     */
    public function listExecutions(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->get('per_page', 25)), 100);

        // Verify tenant can see the report
        ReportDefinition::visibleTo($tenantId)->findOrFail($id);

        $executions = ReportExecution::forTenant($tenantId)
            ->where('report_definition_id', $id)
            ->latest()
            ->paginate($perPage);

        return response()->json($executions);
    }

    /**
     * Get a single execution result.
     *
     * @urlParam id integer required The execution ID. Example: 1
     */
    public function showExecution(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $execution = ReportExecution::forTenant($tenantId)
            ->with('definition:id,name,slug,output_format')
            ->findOrFail($id);

        return response()->json($execution);
    }

    /**
     * Download the PDF or Excel file for a completed execution.
     *
     * Chantier 29: this was the one real, reachable "download my report"
     * button in the app, and it never produced a real PDF or a real XLSX
     * file — it delegated to ReportingService::generatePdf()/generateExcel(),
     * two fakes (a plain-text file saved under a .pdf name; a CSV saved
     * under a .csv name regardless of the requested format's real MIME
     * type). The real DomPDF/PhpSpreadsheet engine — ReportGenerationService
     * — already existed, fully built and already injected into this
     * controller as $this->generation, but was never actually called from
     * here (its only other callers, RunReportJob/DeliverScheduledReportJob,
     * are themselves never dispatched anywhere in the app — a separate,
     * already-documented gap, left untouched). Rewired onto the real engine:
     * the execution's already-persisted result_data (the full row set —
     * ReportingService::execute()'s runQuery() stores it in full, unlike
     * ReportGenerationService::run()'s own preview-only DB write, which
     * this download path never uses) is handed to
     * exportPdf()/exportXlsx(), which render real HTML→PDF via DomPDF and
     * a real .xlsx via PhpSpreadsheet (both installed — confirmed via
     * `composer show`) — never the CSV/HTML fallbacks those two methods
     * also support for an environment without those packages.
     *
     * @urlParam id integer required The execution ID. Example: 1
     * @queryParam format string Export format: pdf or excel (default: excel). Example: pdf
     */
    public function downloadExecution(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $execution = ReportExecution::forTenant($tenantId)->findOrFail($id);

        if (! $execution->isCompleted()) {
            return response()->json(['message' => 'Execution has not completed yet.'], 422);
        }

        $format = $request->get('format', 'excel');
        $data   = $execution->result_data ?? [];

        try {
            $filePath = match ($format) {
                'pdf'   => $this->generation->exportPdf($execution, $data),
                default => $this->generation->exportXlsx($execution, $data),
            };
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $mimeType     = $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $extension    = $format === 'pdf' ? 'pdf' : 'xlsx';
        $downloadName = sprintf('report_%d.%s', $execution->id, $extension);

        return Storage::download($filePath, $downloadName, [
            'Content-Type' => $mimeType,
        ]);
    }

    // ─── Sharing ───────────────────────────────────────────────────────────────

    /**
     * Share a report with a user.
     *
     * @urlParam id integer required The report definition ID. Example: 1
     * @bodyParam shared_with_user_id integer required ID of the user to share with. Example: 5
     * @bodyParam permission string view or edit. Default: view. Example: view
     */
    public function shareReport(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        // Only the tenant owner can share their own report
        $report = ReportDefinition::forTenant($tenantId)->findOrFail($id);

        $validated = $request->validate([
            'shared_with_user_id' => 'required|integer|exists:users,id',
            'permission'          => 'nullable|in:view,edit',
        ]);

        $share = ReportShare::updateOrCreate(
            [
                'report_id'          => $report->id,
                'shared_with_user_id' => $validated['shared_with_user_id'],
            ],
            [
                'permission' => $validated['permission'] ?? 'view',
            ]
        );

        return response()->json($share->load('sharedWith:id,name,email'), 201);
    }

    // ─── Schedules ─────────────────────────────────────────────────────────────

    /**
     * List report schedules for the authenticated tenant.
     *
     * @queryParam per_page integer Results per page (max 100). Example: 25
     */
    public function listSchedules(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->get('per_page', 25)), 100);

        $schedules = ReportSchedule::forTenant($tenantId)
            ->with('definition:id,name,slug')
            ->latest()
            ->paginate($perPage);

        return response()->json($schedules);
    }

    /**
     * Create a report schedule.
     *
     * @bodyParam report_definition_id integer required ID of the report definition. Example: 1
     * @bodyParam name string required Schedule name. Example: Monthly Sales Report
     * @bodyParam frequency string required One of: daily, weekly, monthly, custom. Example: monthly
     * @bodyParam cron_expression string Cron expression (required when frequency=custom). Example: 0 9 1 * *
     * @bodyParam recipients array required Array of recipient email addresses. Example: ["cfo@example.com"]
     */
    public function createSchedule(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'report_definition_id' => 'required|integer|exists:report_definitions,id',
            'name'                 => 'required|string|max:100',
            'frequency'            => 'required|in:daily,weekly,monthly,custom',
            'cron_expression'      => 'nullable|string|max:50|required_if:frequency,custom',
            'recipients'           => 'required|array|min:1',
            'recipients.*'         => 'required|email',
        ]);

        $schedule = ReportSchedule::create([
            'tenant_id'            => $tenantId,
            'report_definition_id' => $validated['report_definition_id'],
            'name'                 => $validated['name'],
            'frequency'            => $validated['frequency'],
            'cron_expression'      => $validated['cron_expression'] ?? null,
            'recipients'           => $validated['recipients'],
            'is_active'            => true,
            'next_run_at'          => now()->addDay(),
        ]);

        return response()->json($schedule->load('definition:id,name,slug'), 201);
    }

    // ─── OHADA Financial Reports ───────────────────────────────────────────────

    /**
     * Bilan SYSCOHADA (Balance Sheet).
     *
     * @queryParam period string required Period: 2026, 2026-Q1, 2026-03. Example: 2026-Q1
     * @queryParam currency string Currency code. Default: XOF. Example: XOF
     */
    public function ohadaBalanceSheet(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $period   = $request->get('period', now()->format('Y'));
        $currency = $request->get('currency', 'XOF');

        $data = $this->ohada->generateBalanceSheet($tenantId, $period, $currency);
        return response()->json($data);
    }

    /**
     * Compte de Résultat SYSCOHADA (Income Statement).
     *
     * @queryParam period string required Period: 2026, 2026-Q1, 2026-03. Example: 2026-Q1
     * @queryParam currency string Currency code. Default: XOF. Example: XOF
     */
    public function ohadaIncomeStatement(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $period   = $request->get('period', now()->format('Y'));
        $currency = $request->get('currency', 'XOF');

        $data = $this->ohada->generateIncomeStatement($tenantId, $period, $currency);
        return response()->json($data);
    }

    /**
     * Balance Générale des Comptes (Trial Balance).
     *
     * @queryParam period string required Period: 2026, 2026-Q1, 2026-03. Example: 2026-03
     */
    public function ohadaTrialBalance(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $period   = $request->get('period', now()->format('Y-m'));

        $data = $this->ohada->generateTrialBalance($tenantId, $period);
        return response()->json($data);
    }

    /**
     * Journal Comptable (Day Book).
     *
     * @queryParam type string Journal type: ventes|achats|caisse|banque|opérations_diverses. Example: ventes
     * @queryParam period string Period. Example: 2026-03
     */
    public function ohadaJournal(Request $request): JsonResponse
    {
        $tenantId    = $this->tenantId($request);
        $journalType = $request->get('type', 'ventes');
        $period      = $request->get('period', now()->format('Y-m'));

        $data = $this->ohada->generateJournal($tenantId, $journalType, $period);
        return response()->json($data);
    }

    /**
     * Balance Âgée Clients (Aged Receivables).
     *
     * @queryParam as_of string As-of date (YYYY-MM-DD). Example: 2026-05-25
     */
    public function ohadaAgedReceivables(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $asOf     = $request->get('as_of', now()->toDateString());

        $data = $this->ohada->generateAgedReceivables($tenantId, $asOf);
        return response()->json($data);
    }

    /**
     * Balance Âgée Fournisseurs (Aged Payables).
     *
     * @queryParam as_of string As-of date (YYYY-MM-DD). Example: 2026-05-25
     */
    public function ohadaAgedPayables(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $asOf     = $request->get('as_of', now()->toDateString());

        $data = $this->ohada->generateAgedPayables($tenantId, $asOf);
        return response()->json($data);
    }

    /**
     * Déclaration TVA (VAT Return).
     *
     * @queryParam period string Period: 2026-Q1 or 2026-03. Example: 2026-Q1
     */
    public function ohadaTva(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $period   = $request->get('period', now()->format('Y-m'));

        $data = $this->ohada->generateTvaReport($tenantId, $period);
        return response()->json($data);
    }

    /**
     * Impôt sur les Sociétés (IS – Corporate Income Tax).
     *
     * @queryParam fiscal_year string Fiscal year (YYYY). Example: 2025
     */
    public function ohadaIs(Request $request): JsonResponse
    {
        $tenantId   = $this->tenantId($request);
        $fiscalYear = $request->get('fiscal_year', (string) (now()->year - 1));

        $data = $this->ohada->generateIsReport($tenantId, $fiscalYear);
        return response()->json($data);
    }

    // ─── Natural Language Query ────────────────────────────────────────────────

    /**
     * Convert a natural language question to SQL and execute it.
     *
     * @bodyParam query string required The NL question in French or English. Example: "Quelles sont les ventes du mois ?"
     * @bodyParam locale string Language (fr|en). Default: fr. Example: fr
     * @bodyParam save_as string Optional name to save the query. Example: Ventes mensuelles
     */
    public function nlQuery(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'query'   => 'required|string|max:500',
            'locale'  => 'nullable|in:fr,en',
            'save_as' => 'nullable|string|max:150',
        ]);

        $locale = $validated['locale'] ?? 'fr';
        $result = $this->nlSql->translate($validated['query'], $tenantId, $locale);

        // Optionally save the query
        if (! empty($validated['save_as']) && ! empty($result['sql'])) {
            $saved = $this->nlSql->saveQuery(
                $validated['save_as'],
                $validated['query'],
                $tenantId,
                $request->user()->id,
                'nl',
            );
            $result['saved_query_id'] = $saved->id;
        }

        return response()->json($result);
    }

    /**
     * List saved queries for the authenticated tenant.
     */
    public function listSavedQueries(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $userId   = $request->user()->id;
        $perPage  = min((int) ($request->get('per_page', 25)), 100);

        $queries = SavedQuery::visibleTo($tenantId, $userId)
            ->with('createdBy:id,name')
            ->latest()
            ->paginate($perPage);

        return response()->json($queries);
    }

    /**
     * Save a query (NL or SQL).
     *
     * @bodyParam name string required Query name. Example: Top clients inactifs
     * @bodyParam query_text string required The NL or SQL query text. Example: Clients sans commande depuis 60 jours
     * @bodyParam query_type string nl|sql. Default: nl. Example: nl
     * @bodyParam is_shared boolean Share with all users. Default: false. Example: false
     */
    public function storeSavedQuery(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'query_text'  => 'required|string',
            'query_type'  => 'nullable|in:sql,nl,graphql',
            'description' => 'nullable|string|max:500',
            'is_shared'   => 'nullable|boolean',
        ]);

        $query = SavedQuery::create([
            'tenant_id'   => $tenantId,
            'name'        => $validated['name'],
            'query_text'  => $validated['query_text'],
            'query_type'  => $validated['query_type'] ?? 'nl',
            'description' => $validated['description'] ?? null,
            'created_by'  => $request->user()->id,
            'is_shared'   => $validated['is_shared'] ?? false,
        ]);

        return response()->json($query, 201);
    }

    // ─── Dashboards ────────────────────────────────────────────────────────────

    /**
     * List dashboards for the authenticated tenant.
     */
    public function listDashboards(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $perPage  = min((int) ($request->get('per_page', 25)), 100);

        $items = Dashboard::forTenant($tenantId)
            ->with('owner:id,name')
            ->latest()
            ->paginate($perPage);

        return response()->json($items);
    }

    /**
     * Create a new dashboard.
     *
     * @bodyParam name string required Dashboard name. Example: Finance Q2-2026
     * @bodyParam description string Optional. Example: Suivi financier trimestriel
     * @bodyParam is_default boolean Set as default dashboard. Default: false. Example: false
     * @bodyParam industry string Industry hint for default widgets: commerce|textile|services. Example: commerce
     */
    public function storeDashboard(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $validated = $request->validate([
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'is_default'  => 'nullable|boolean',
            'industry'    => 'nullable|string|max:50',
            'template'    => 'nullable|string|max:50',
            'shared_with' => 'nullable|array',
        ]);

        if (! empty($validated['template'])) {
            $dashboard = $this->dashboards->cloneTemplate($validated['template'], $tenantId);
        } elseif (! empty($validated['industry'])) {
            $dashboard = $this->dashboards->createDefaultDashboard($tenantId, $validated['industry']);
            $dashboard->update([
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);
        } else {
            $dashboard = Dashboard::create([
                'tenant_id'   => $tenantId,
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default'  => $validated['is_default'] ?? false,
                // Chantier 19 (Lot 5): 'owner_id' → 'created_by', the real
                // column — see Dashboard model's own docblock for the fix.
                'created_by'  => $request->user()->id,
                'shared_with' => $validated['shared_with'] ?? [],
            ]);
        }

        return response()->json($dashboard->load('widgets'), 201);
    }

    /**
     * Get a dashboard with its widgets and live data.
     *
     * @urlParam id integer required The dashboard ID. Example: 1
     */
    public function showDashboard(Request $request, int $id): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $dashboard = Dashboard::forTenant($tenantId)->with('widgets')->findOrFail($id);

        $widgetsData = $this->dashboards->getDashboardWidgetsData($dashboard);

        return response()->json([
            'dashboard'    => $dashboard,
            'widgets_data' => $widgetsData,
        ]);
    }

    /**
     * Update a dashboard's layout and metadata.
     *
     * @urlParam id integer required The dashboard ID. Example: 1
     */
    public function updateDashboard(Request $request, int $id): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $dashboard = Dashboard::forTenant($tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string|max:500',
            'is_default'  => 'sometimes|boolean',
            'layout'      => 'nullable|array',
            'shared_with' => 'nullable|array',
        ]);

        $dashboard->update($validated);

        return response()->json($dashboard->fresh()->load('widgets'));
    }

    /**
     * Generate an AI narrative summary of a dashboard.
     *
     * @urlParam id integer required Dashboard ID. Example: 1
     * @queryParam locale string fr|en. Default: fr. Example: fr
     */
    public function dashboardAiSummary(Request $request, int $id): JsonResponse
    {
        $tenantId  = $this->tenantId($request);
        $dashboard = Dashboard::forTenant($tenantId)->with('widgets')->findOrFail($id);
        $locale    = $request->get('locale', 'fr');

        $summary = $this->dashboards->generateAiSummary($dashboard, $locale);

        return response()->json(['summary' => $summary]);
    }

    /**
     * Get refreshed data for a single widget.
     *
     * @urlParam id integer required The widget ID. Example: 1
     */
    public function widgetData(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $widget   = ReportWidget::forTenant($tenantId)->findOrFail($id);

        $data = $this->dashboards->getWidgetData($widget);

        return response()->json($data);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Chantier 8 (Reporting): every method in this controller used to inline
     * `$request->user()->tenant_id ?? 1` (~30 call sites). users.tenant_id is
     * a real DB column (added defensively by an early migration) but is NOT
     * in App\Models\User::$fillable and nothing in the real registration/
     * onboarding flow ever populates it — it is always null in practice, so
     * every company's users transparently shared the same tenant_id=1 bucket
     * of report definitions, dashboards, schedules, executions, saved
     * queries and shares. A real, live cross-tenant leak, not hypothetical.
     *
     * The real multi-tenant boundary column is users.company_id (confirmed
     * via App\Http\Middleware\InitializeTenancyFromAuthenticatedUser's own
     * docblock) — same ID-space mismatch bug pattern already fixed
     * repeatedly elsewhere in this app (Setup's SetupController/
     * OnboardingMetricsController, LeaveRequestPolicy, PayrollPolicy,
     * TimesheetEntryPolicy, Security's company_id-type-mismatch policies).
     * No header fallback (unlike Setup's now-fixed X-Company-ID exploit) —
     * there never was one here.
     */
    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id ?? 0);
    }
}
