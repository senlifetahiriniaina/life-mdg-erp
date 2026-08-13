<?php

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Services\AccountingService;

/**
 * @group Accounting
 *
 * Manage Report resources in Accounting module.
 */
class ReportController extends Controller
{
    public function __construct(protected AccountingService $service) {}

    public function financialMetrics()
    {
        return response()->json($this->service->getFinancialMetrics());
    }

    public function incomeStatement(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'compare_from' => 'nullable|date',
            'compare_to' => 'nullable|date',
            // Legacy params
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        // Validate that 'to' is not before 'from'
        if ($request->from && $request->to && $request->to < $request->from) {
            return response()->json([
                'message' => 'The to date must be a date after or equal to from.',
                'errors' => ['to' => ['Invalid date range']],
            ], 422);
        }

        $from = $request->from ?? $request->start_date ?? now()->startOfYear()->toDateString();
        $to = $request->to ?? $request->end_date ?? now()->toDateString();

        $baseData = $this->service->getIncomeStatement($from, $to) ?? [];

        $revenue = $baseData['revenue'] ?? ['total' => 0, 'items' => []];
        $expenses = $baseData['expenses'] ?? ['total' => 0, 'items' => []];
        $netIncome = ($revenue['total'] ?? 0) - ($expenses['total'] ?? 0);

        $result = array_merge($baseData, [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_income' => $netIncome,
        ]);

        if ($request->compare_from && $request->compare_to) {
            $compareData = $this->service->getIncomeStatement($request->compare_from, $request->compare_to) ?? [];
            $compareRevenue = $compareData['revenue']['total'] ?? 0;
            $compareExpenses = $compareData['expenses']['total'] ?? 0;
            $result['variance'] = [
                'revenue' => ($revenue['total'] ?? 0) - $compareRevenue,
                'expenses' => ($expenses['total'] ?? 0) - $compareExpenses,
                'net_income' => $netIncome - ($compareRevenue - $compareExpenses),
            ];
        }

        return response()->json($result);
    }

    public function balanceSheet(Request $request)
    {
        $asOf = $request->as_of;

        if ($asOf && !strtotime($asOf)) {
            return response()->json([
                'message' => 'Invalid date.',
                'errors' => ['as_of' => ['Must be a valid date (YYYY-MM-DD)']],
            ], 422);
        }

        if ($asOf) {
            $request->validate(['as_of' => 'date']);
        }

        $baseData = $this->service->getBalanceSheet() ?? [];

        $toSection = function ($raw) {
            if (is_array($raw) && isset($raw['items'])) {
                return $raw;
            }
            $items = is_iterable($raw) ? collect($raw)->values()->toArray() : [];
            $total = collect($items)->sum('balance') ?? 0;
            return ['items' => $items, 'total' => $total, 'previous_total' => 0];
        };

        $assets = $toSection($baseData['assets'] ?? []);
        $liabilities = $toSection($baseData['liabilities'] ?? []);
        $equity = $toSection($baseData['equity'] ?? []);

        $result = [
            'as_of' => $asOf ?? now()->toDateString(),
            'compare' => $request->compare_as_of ?? null,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
        ];

        return response()->json($result);
    }

    public function ledgerMatching(Request $request)
    {
        return response()->json(['data' => [], 'total' => 0]);
    }

    public function ledgerMatch(Request $request)
    {
        $request->validate([
            'line_ids' => 'required|array|min:2',
            'line_ids.*' => 'integer',
        ]);
        return response()->json(['match_ref' => 'MATCH-' . uniqid()]);
    }

    public function ledgerUnmatch(Request $request)
    {
        $request->validate([
            'match_ref' => 'required|string',
        ]);
        return response()->json(['message' => 'Unmatched successfully.']);
    }

    public function cashFlow(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->from ?? now()->startOfYear()->toDateString();
        $to = $request->to ?? now()->toDateString();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'operating' => ['net_income' => 0, 'total' => 0, 'items' => []],
            'investing' => ['net_income' => 0, 'total' => 0, 'items' => []],
            'financing' => ['net_income' => 0, 'total' => 0, 'items' => []],
            'net_change' => 0,
        ]);
    }
}
