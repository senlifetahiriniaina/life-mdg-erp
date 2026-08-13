<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Invoice;

class FinancialReportService
{
    /**
     * Balance sheet (Bilan) at a given date.
     * Computes accumulated debit/credit per account type from posted journal entries.
     */
    public function balanceSheet(Carbon $asOf, ?Carbon $compareAsOf = null): array
    {
        $current = $this->accountBalances($asOf);
        $previous = $compareAsOf ? $this->accountBalances($compareAsOf) : null;

        return [
            'as_of' => $asOf->toDateString(),
            'compare' => $compareAsOf?->toDateString(),
            'assets' => $this->buildSection($current, $previous, ['asset']),
            'liabilities' => $this->buildSection($current, $previous, ['liability']),
            'equity' => $this->buildSection($current, $previous, ['equity']),
        ];
    }

    /**
     * Income statement (Compte de résultat) for a period, optionally compared to a previous period.
     */
    public function incomeStatement(Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null): array
    {
        $current = $this->periodBalances($from, $to);
        $previous = ($compareFrom && $compareTo) ? $this->periodBalances($compareFrom, $compareTo) : null;

        $revenue = $this->buildSection($current, $previous, ['revenue']);
        $expenses = $this->buildSection($current, $previous, ['expense']);

        $currentTotal = $revenue['total'] - $expenses['total'];
        $previousTotal = $previous ? ($revenue['previous_total'] - $expenses['previous_total']) : null;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'compare_from' => $compareFrom?->toDateString(),
            'compare_to' => $compareTo?->toDateString(),
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_income' => $currentTotal,
            'net_income_previous' => $previousTotal,
            'variance' => $previousTotal !== null ? $currentTotal - $previousTotal : null,
        ];
    }

    /**
     * Cash flow statement (indirect method) for a period.
     */
    public function cashFlow(Carbon $from, Carbon $to): array
    {
        // Operating: net income adjusted for non-cash items
        $is = $this->incomeStatement($from, $to);
        $netIncome = $is['net_income'];

        // Collect cash movements from bank/cash journal entries
        $cashAccountIds = ChartOfAccount::where('type', 'asset')
            ->where(function ($q) {
                $q->where('sub_type', 'cash')
                    ->orWhere('sub_type', 'bank')
                    ->orWhere('name', 'like', '%Caisse%')
                    ->orWhere('name', 'like', '%Banque%')
                    ->orWhere('name', 'like', '%Cash%')
                    ->orWhere('name', 'like', '%Bank%');
            })
            ->pluck('id');

        $cashMovements = DB::table('acc_journal_entry_lines as jel')
            ->join('acc_journal_entries as je', 'je.id', '=', 'jel.entry_id')
            ->whereIn('jel.account_id', $cashAccountIds)
            ->whereBetween('je.date', [$from->toDateString(), $to->toDateString()])
            ->where('je.status', 'posted')
            ->selectRaw('SUM(jel.debit) - SUM(jel.credit) as net_cash')
            ->value('net_cash') ?? 0;

        // AR / AP movements
        $arChange = $this->receivablesChange($from, $to);
        $apChange = $this->payablesChange($from, $to);

        $operating = $netIncome - $arChange + $apChange;

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'operating' => [
                'net_income' => round($netIncome, 2),
                'receivables_change' => round(-$arChange, 2),
                'payables_change' => round($apChange, 2),
                'total' => round($operating, 2),
            ],
            'investing' => [
                'total' => 0,
                'items' => [],
            ],
            'financing' => [
                'total' => 0,
                'items' => [],
            ],
            'net_change' => round((float) $cashMovements, 2),
        ];
    }

    /**
     * Returns unmatch or partially matched invoice lines for lettrage.
     */
    public function unmatchedLines(?string $accountCode = null): Collection
    {
        return DB::table('acc_invoice_lines as il')
            ->join('acc_invoices as i', 'i.id', '=', 'il.invoice_id')
            ->join('acc_chart_of_accounts as coa', 'coa.id', '=', 'il.account_id')
            ->whereNull('il.match_ref')
            ->where('i.status', '!=', 'draft')
            ->when($accountCode, fn ($q) => $q->where('coa.code', 'like', "{$accountCode}%"))
            ->select([
                'il.id', 'il.invoice_id', 'i.number as invoice_number', 'i.type as invoice_type',
                'i.partner_name', 'il.account_id', 'coa.code as account_code', 'coa.name as account_name',
                'il.quantity', 'il.unit_price', 'il.total', 'il.matched_at',
            ])
            ->get();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function accountBalances(Carbon $asOf): Collection
    {
        return DB::table('acc_journal_entry_lines as jel')
            ->join('acc_journal_entries as je', 'je.id', '=', 'jel.entry_id')
            ->join('acc_chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOf->toDateString())
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.type', 'coa.sub_type', 'coa.parent_id')
            ->selectRaw('coa.id, coa.code, coa.name, coa.type, coa.sub_type, coa.parent_id, SUM(jel.debit) - SUM(jel.credit) as balance')
            ->get()
            ->keyBy('id');
    }

    private function periodBalances(Carbon $from, Carbon $to): Collection
    {
        return DB::table('acc_journal_entry_lines as jel')
            ->join('acc_journal_entries as je', 'je.id', '=', 'jel.entry_id')
            ->join('acc_chart_of_accounts as coa', 'coa.id', '=', 'jel.account_id')
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('coa.id', 'coa.code', 'coa.name', 'coa.type', 'coa.sub_type', 'coa.parent_id')
            ->selectRaw('coa.id, coa.code, coa.name, coa.type, coa.sub_type, coa.parent_id, SUM(jel.credit) - SUM(jel.debit) as balance')
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, mixed>  $current
     * @param  Collection<int, mixed>|null  $previous
     * @param  array<string>  $types
     */
    private function buildSection(Collection $current, ?Collection $previous, array $types): array
    {
        $lines = $current->filter(fn ($row) => in_array($row->type, $types, true));

        $items = $lines->map(fn ($row) => [
            'id' => $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'type' => $row->type,
            'sub_type' => $row->sub_type ?? null,
            'balance' => round((float) $row->balance, 2),
            'prev_balance' => $previous ? round((float) ($previous->get($row->id)?->balance ?? 0), 2) : null,
        ])->values();

        $total = $items->sum('balance');
        $prevTotal = $previous ? $items->sum('prev_balance') : null;

        return [
            'items' => $items,
            'total' => round($total, 2),
            'previous_total' => $prevTotal !== null ? round($prevTotal, 2) : null,
        ];
    }

    private function receivablesChange(Carbon $from, Carbon $to): float
    {
        $open = Invoice::where('type', 'invoice')->where('invoice_date', '<', $from)->sum('amount_due');
        $close = Invoice::where('type', 'invoice')->where('invoice_date', '<=', $to)->sum('amount_due');

        return (float) $close - (float) $open;
    }

    private function payablesChange(Carbon $from, Carbon $to): float
    {
        $open = Invoice::where('type', 'vendor_bill')->where('invoice_date', '<', $from)->sum('amount_due');
        $close = Invoice::where('type', 'vendor_bill')->where('invoice_date', '<=', $to)->sum('amount_due');

        return (float) $close - (float) $open;
    }
}
