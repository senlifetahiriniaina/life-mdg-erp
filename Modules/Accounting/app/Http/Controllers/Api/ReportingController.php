<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Accounting\Exports\OhadaBalanceSheetExport;
use Modules\Accounting\Exports\OhadaIncomeStatementExport;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\FinancialReportService;
use Modules\Reporting\Services\OhadaReportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Accounting - Financial Reporting
 *
 * Generate, store, and export financial reports (income statement, balance sheet, cash flow, tax summary).
 */
class ReportingController extends Controller
{
    public function __construct(private FinancialReportService $service) {}

    /**
     * Chantier 18: `FinancialReportService::balanceSheet()`/`incomeStatement()`
     * (used above) group by the flat `type` column (asset/liability/equity/
     * revenue/expense) — not the SYSCOHADA/Madagascar rubrique structure
     * (Actif immobilisé/Stocks/Créances/Trésorerie-actif;
     * Capitaux propres/Dettes financières/Passif circulant/Trésorerie-passif;
     * CA→marge→résultat d'exploitation→financier→net). That real structure
     * already exists in `Modules\Reporting\Services\OhadaReportService`
     * (also fixed this chantier — it was reading from a phantom, never-
     * migrated table pair) — delegated to here rather than duplicated, so
     * Accounting's own reports page (where users actually look for
     * financial statements) can render the real Madagascar-standard
     * presentation under this module's own RBAC gate.
     *
     * POST /accounting/reports/ohada/balance-sheet
     */
    public function ohadaBalanceSheet(Request $request, OhadaReportService $ohada): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string',
        ]);

        $period = $validated['period'] ?? now()->format('Y-m');

        return response()->json([
            'data' => $ohada->generateBalanceSheet($request->user()?->company_id ?? 0, $period, 'MGA'),
        ]);
    }

    /** POST /accounting/reports/ohada/income-statement — see ohadaBalanceSheet() docblock. */
    public function ohadaIncomeStatement(Request $request, OhadaReportService $ohada): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string',
        ]);

        $period = $validated['period'] ?? now()->format('Y-m');

        return response()->json([
            'data' => $ohada->generateIncomeStatement($request->user()?->company_id ?? 0, $period, 'MGA'),
        ]);
    }

    /** GET /financial-reports — List saved reports. */
    public function index(Request $request): JsonResponse
    {
        // Financial reports are generated on-the-fly; return a manifest of report types.
        return response()->json([
            'data' => [
                ['type' => 'income_statement',    'label' => 'Compte de résultat'],
                ['type' => 'balance_sheet',        'label' => 'Bilan'],
                ['type' => 'cash_flow',            'label' => 'Flux de trésorerie'],
                ['type' => 'tax_summary',          'label' => 'Résumé fiscal'],
                ['type' => 'multi_period',         'label' => 'Comparaison multi-périodes'],
            ],
        ]);
    }

    /** GET /financial-reports/{report} */
    public function show(string $report): JsonResponse
    {
        return response()->json([
            'data' => [
                'type'        => $report,
                'description' => "Report type: {$report}. Use POST endpoints to generate.",
            ],
        ]);
    }

    /** POST /financial-reports/income-statement */
    public function incomeStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'         => 'required|date',
            'to'           => 'required|date|after_or_equal:from',
            'compare_from' => 'nullable|date',
            'compare_to'   => 'nullable|date',
        ]);

        $from        = Carbon::parse($validated['from']);
        $to          = Carbon::parse($validated['to']);
        $compareFrom = isset($validated['compare_from']) ? Carbon::parse($validated['compare_from']) : null;
        $compareTo   = isset($validated['compare_to'])   ? Carbon::parse($validated['compare_to'])   : null;

        $report = $this->service->incomeStatement($from, $to, $compareFrom, $compareTo);

        return response()->json(['data' => $report]);
    }

    /** POST /financial-reports/balance-sheet */
    public function balanceSheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of'       => 'required|date',
            'compare_as_of' => 'nullable|date',
        ]);

        $asOf        = Carbon::parse($validated['as_of']);
        $compareAsOf = isset($validated['compare_as_of']) ? Carbon::parse($validated['compare_as_of']) : null;

        $report = $this->service->balanceSheet($asOf, $compareAsOf);

        return response()->json(['data' => $report]);
    }

    /** POST /financial-reports/cash-flow */
    public function cashFlowStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $report = $this->service->cashFlow(Carbon::parse($validated['from']), Carbon::parse($validated['to']));

        return response()->json(['data' => $report]);
    }

    /** POST /financial-reports/tax-summary */
    public function taxSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'    => 'required|date',
            'to'      => 'required|date|after_or_equal:from',
            'country' => 'nullable|string|size:2',
        ]);

        $is = $this->service->incomeStatement(
            Carbon::parse($validated['from']),
            Carbon::parse($validated['to'])
        );

        return response()->json([
            'data' => [
                'period'  => ['from' => $validated['from'], 'to' => $validated['to']],
                'country' => $validated['country'] ?? null,
                'revenue' => $is['revenue'] ?? [],
                'expenses'=> $is['expenses'] ?? [],
            ],
        ]);
    }

    /** POST /financial-reports/multi-period */
    public function multiPeriodComparison(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periods'      => 'required|array|min:2',
            'periods.*.from' => 'required|date',
            'periods.*.to'   => 'required|date',
            'type'         => 'required|in:income_statement,balance_sheet,cash_flow',
        ]);

        $results = [];
        foreach ($validated['periods'] as $period) {
            $from = Carbon::parse($period['from']);
            $to   = Carbon::parse($period['to']);

            $results[] = match ($validated['type']) {
                'balance_sheet'  => $this->service->balanceSheet($to),
                'cash_flow'      => $this->service->cashFlow($from, $to),
                default          => $this->service->incomeStatement($from, $to),
            };
        }

        return response()->json(['data' => $results]);
    }

    /** GET /dashboard/kpi */
    public function dashboard(Request $request): JsonResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfYear()->toDateString()));
        $to   = Carbon::parse($request->query('to',   now()->toDateString()));

        $is = $this->service->incomeStatement($from, $to);
        $bs = $this->service->balanceSheet($to);

        return response()->json([
            'data' => [
                'income_statement' => $is,
                'balance_sheet'    => $bs,
                'period'           => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ],
        ]);
    }

    /** GET /accounts/{accountId}/drilldown */
    public function accountDrilldown(int $accountId): JsonResponse
    {
        $account = ChartOfAccount::findOrFail($accountId);

        return response()->json(['data' => $account->load('journalEntries')]);
    }

    /** GET /gl-accounts/{accountCode}/drilldown */
    public function glDrilldown(string $accountCode): JsonResponse
    {
        $unmatched = $this->service->unmatchedLines($accountCode);

        return response()->json(['data' => $unmatched, 'account_code' => $accountCode]);
    }

    /** GET /account-types/{accountType}/detail */
    public function accountTypeDetail(string $accountType): JsonResponse
    {
        $accounts = ChartOfAccount::where('account_type', $accountType)->get();

        return response()->json(['data' => $accounts, 'account_type' => $accountType]);
    }

    /** POST /financial-reports/{report}/publish */
    public function publish(string $report): JsonResponse
    {
        return response()->json(['data' => ['report' => $report, 'status' => 'published', 'published_at' => now()]]);
    }

    /** POST /financial-reports/{report}/review */
    public function review(Request $request, string $report): JsonResponse
    {
        return response()->json([
            'data' => [
                'report'      => $report,
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'notes'       => $request->input('notes'),
            ],
        ]);
    }

    /**
     * Chantier 29: real implementation — these two endpoints used to be pure
     * stubs returning `{"message":"Export queued."}` JSON with no file ever
     * generated, despite being routed and reachable. Delegates to the exact
     * same real, already-working `OhadaReportService::generateBalanceSheet()`/
     * `generateIncomeStatement()` the on-screen `BalanceSheet.vue`/
     * `IncomeStatement.vue` pages already call via `ohadaBalanceSheet()`/
     * `ohadaIncomeStatement()` above (Chantier 18) — so the export always
     * matches what the user sees on screen, and no report logic is
     * duplicated. Supports "balance-sheet"/"balance_sheet" and
     * "income-statement"/"income_statement" (the two OHADA statements this
     * module has real screen data for); any other `{report}` value 404s
     * rather than silently returning an empty/wrong file.
     *
     * GET /financial-reports/{report}/export/excel?period=YYYY-MM
     */
    public function exportExcel(Request $request, string $report, OhadaReportService $ohada): BinaryFileResponse
    {
        $type      = $this->normalizeReportType($report);
        $period    = (string) $request->query('period', now()->format('Y-m'));
        $companyId = (int) ($request->user()?->company_id ?? 0);

        if ($type === 'balance-sheet') {
            $data = $ohada->generateBalanceSheet($companyId, $period, 'MGA');

            return Excel::download(new OhadaBalanceSheetExport($data), "bilan-syscohada-{$period}.xlsx");
        }

        if ($type === 'income-statement') {
            $data = $ohada->generateIncomeStatement($companyId, $period, 'MGA');

            return Excel::download(new OhadaIncomeStatementExport($data), "compte-de-resultat-syscohada-{$period}.xlsx");
        }

        abort(404, "Export non pris en charge pour le rapport « {$report} ». Types supportés : balance-sheet, income-statement.");
    }

    /** GET /financial-reports/{report}/export/pdf?period=YYYY-MM — see exportExcel() docblock. */
    public function exportPdf(Request $request, string $report, OhadaReportService $ohada): Response
    {
        $type      = $this->normalizeReportType($report);
        $period    = (string) $request->query('period', now()->format('Y-m'));
        $companyId = (int) ($request->user()?->company_id ?? 0);

        if ($type === 'balance-sheet') {
            $data = $ohada->generateBalanceSheet($companyId, $period, 'MGA');
            $pdf  = Pdf::loadView('accounting.financial-reports.balance-sheet', ['data' => $data])->setPaper('a4', 'portrait');

            return $pdf->download("bilan-syscohada-{$period}.pdf");
        }

        if ($type === 'income-statement') {
            $data = $ohada->generateIncomeStatement($companyId, $period, 'MGA');
            $pdf  = Pdf::loadView('accounting.financial-reports.income-statement', ['data' => $data])->setPaper('a4', 'portrait');

            return $pdf->download("compte-de-resultat-syscohada-{$period}.pdf");
        }

        abort(404, "Export non pris en charge pour le rapport « {$report} ». Types supportés : balance-sheet, income-statement.");
    }

    /** Normalize a route {report} slug ("balance_sheet", "Balance-Sheet", …) to its canonical hyphenated form. */
    private function normalizeReportType(string $report): string
    {
        return str_replace('_', '-', strtolower($report));
    }

    /** POST /financial-reports/export/multiple */
    public function exportMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate(['reports' => 'required|array', 'format' => 'required|in:excel,pdf,csv']);

        return response()->json([
            'data' => [
                'reports' => $validated['reports'],
                'format'  => $validated['format'],
                'message' => 'Bulk export queued.',
            ],
        ]);
    }
}
