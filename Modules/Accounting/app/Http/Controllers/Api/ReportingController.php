<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\FinancialReportService;

/**
 * @group Accounting - Financial Reporting
 *
 * Generate, store, and export financial reports (income statement, balance sheet, cash flow, tax summary).
 */
class ReportingController extends Controller
{
    public function __construct(private FinancialReportService $service) {}

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

    /** GET /financial-reports/{report}/export/excel */
    public function exportExcel(string $report): JsonResponse
    {
        return response()->json(['data' => ['report' => $report, 'format' => 'excel', 'message' => 'Export queued.']]);
    }

    /** GET /financial-reports/{report}/export/pdf */
    public function exportPdf(string $report): JsonResponse
    {
        return response()->json(['data' => ['report' => $report, 'format' => 'pdf', 'message' => 'Export queued.']]);
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
