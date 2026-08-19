<?php

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\InvoiceLine;
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

    /**
     * "Lettrage" — customer/supplier account reconciliation. Was a literal stub
     * (`return response()->json(['data' => [], 'total' => 0])`) despite the real,
     * routed Lettrage.vue page calling this exact endpoint on every load, and despite
     * `InvoiceLine` already carrying real `match_ref`/`matched_by`/`matched_at`
     * columns clearly designed for this feature — confirmed empirically (Chantier 19
     * re-verification) that the page always showed "Aucune ligne non lettrée trouvée"
     * regardless of real unmatched data. Also fixes a second bug the stub was masking:
     * the frontend does `const { data } = await axios.get(...); lines.value = data`
     * — a bare array is expected, not the `{data:[], total:0}` envelope the stub
     * returned (which would have rendered garbage the moment real rows existed).
     */
    public function ledgerMatching(Request $request)
    {
        $request->validate([
            'account_code' => 'nullable|string',
        ]);

        $lines = InvoiceLine::query()
            ->with(['invoice:id,number,type,partner_name,customer_name', 'account:id,code,name'])
            ->whereNull('match_ref')
            ->whereHas('invoice')
            ->when($request->filled('account_code'), fn ($q) => $q->whereHas(
                'account',
                fn ($aq) => $aq->where('code', 'like', $request->string('account_code') . '%')
            ))
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (InvoiceLine $line) => [
                'id' => $line->id,
                'invoice_type' => $line->invoice?->type,
                'invoice_number' => $line->invoice?->number,
                'partner_name' => $line->invoice?->partner_name ?: $line->invoice?->customer_name,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'description' => $line->description,
                'total' => (float) $line->total,
                'match_ref' => $line->match_ref,
            ]);

        return response()->json($lines);
    }

    public function ledgerMatch(Request $request)
    {
        $request->validate([
            'line_ids' => 'required|array|min:2',
            'line_ids.*' => 'integer|exists:acc_invoice_lines,id',
        ]);

        $matchRef = 'MATCH-' . uniqid();

        InvoiceLine::whereIn('id', $request->input('line_ids'))
            ->whereNull('match_ref')
            ->update([
                'match_ref' => $matchRef,
                'matched_by' => $request->user()?->id,
                'matched_at' => now(),
            ]);

        return response()->json(['match_ref' => $matchRef]);
    }

    public function ledgerUnmatch(Request $request)
    {
        $request->validate([
            'match_ref' => 'required|string',
        ]);

        InvoiceLine::where('match_ref', $request->string('match_ref'))
            ->update(['match_ref' => null, 'matched_by' => null, 'matched_at' => null]);

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
