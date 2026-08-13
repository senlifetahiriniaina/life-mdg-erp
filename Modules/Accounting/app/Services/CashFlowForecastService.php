<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\CashFlowForecast;
use Modules\Accounting\Models\CashFlowForecastItem;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\TreasuryAlert;

class CashFlowForecastService
{
    /**
     * Generate a new cash flow forecast from a base date.
     * Populates inflows from open AR invoices, outflows from open AP bills.
     */
    public function generate(array $attributes, int $userId): CashFlowForecast
    {
        $baseDate = Carbon::parse($attributes['base_date']);
        $endDate = $this->resolveEndDate($baseDate, $attributes['horizon']);

        $openingBalance = (float) ($attributes['opening_balance'] ?? $this->getCurrentCashBalance());

        $forecast = CashFlowForecast::create([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'base_date' => $baseDate,
            'horizon' => $attributes['horizon'],
            'end_date' => $endDate,
            'scenario' => $attributes['scenario'] ?? 'base',
            'opening_balance' => $openingBalance,
            'projected_closing_balance' => $openingBalance,
            'minimum_balance_threshold' => $attributes['minimum_balance_threshold'] ?? null,
            'assumptions' => $attributes['assumptions'] ?? null,
            'status' => 'draft',
            'created_by' => $userId,
        ]);

        $this->populateFromReceivables($forecast, $baseDate, $endDate);
        $this->populateFromPayables($forecast, $baseDate, $endDate);
        $this->populateFromRecurring($forecast, $baseDate, $endDate, $attributes['scenario'] ?? 'base');

        $this->recalculate($forecast);

        return $forecast->load('items');
    }

    /**
     * Rebuild the forecast's running cash balance from its items.
     */
    public function recalculate(CashFlowForecast $forecast): CashFlowForecast
    {
        $items = $forecast->items()->orderBy('date')->get();

        $net = $items->reduce(function (float $carry, CashFlowForecastItem $item) {
            $weightedAmount = (float) $item->weighted_amount;
            $delta = $item->type === 'inflow' ? $weightedAmount : -$weightedAmount;

            return $carry + $delta;
        }, 0.0);

        $projected = (float) $forecast->opening_balance + $net;

        $forecast->update(['projected_closing_balance' => $projected]);

        return $forecast->refresh();
    }

    /**
     * Add a manual forecast item and recalculate.
     */
    public function addItem(CashFlowForecast $forecast, array $data): CashFlowForecastItem
    {
        $probability = (float) ($data['probability'] ?? 100);
        $amount = (float) $data['amount'];

        $item = CashFlowForecastItem::create([
            'forecast_id' => $forecast->id,
            'date' => $data['date'],
            'category' => $data['category'],
            'type' => $data['type'],
            'source' => $data['source'],
            'description' => $data['description'] ?? null,
            'amount' => $amount,
            'probability' => $probability,
            'weighted_amount' => $amount * ($probability / 100),
            'is_actual' => (bool) ($data['is_actual'] ?? false),
        ]);

        $this->recalculate($forecast);

        return $item;
    }

    /**
     * Build a daily/weekly view of the forecast for chart rendering.
     */
    public function buildTimeline(CashFlowForecast $forecast): array
    {
        $items = $forecast->items()->orderBy('date')->get()->groupBy(
            fn (CashFlowForecastItem $i) => $i->date->toDateString()
        );

        $balance = (float) $forecast->opening_balance;
        $timeline = [];
        $current = Carbon::parse($forecast->base_date);
        $end = Carbon::parse($forecast->end_date);
        $threshold = $forecast->minimum_balance_threshold !== null
                       ? (float) $forecast->minimum_balance_threshold
                       : null;

        while ($current->lte($end)) {
            $dateKey = $current->toDateString();
            $dayItems = $items->get($dateKey, collect());

            $inflows = $dayItems->where('type', 'inflow')->sum('weighted_amount');
            $outflows = $dayItems->where('type', 'outflow')->sum('weighted_amount');
            $balance += $inflows - $outflows;

            $timeline[] = [
                'date' => $dateKey,
                'inflows' => round($inflows, 2),
                'outflows' => round($outflows, 2),
                'net' => round($inflows - $outflows, 2),
                'running_balance' => round($balance, 2),
                'below_threshold' => $threshold !== null && $balance < $threshold,
                'items' => $dayItems->values()->toArray(),
            ];

            $current->addDay();
        }

        return $timeline;
    }

    /**
     * Return a summary with KPIs: total inflows, outflows, net, runway days.
     */
    public function buildSummary(CashFlowForecast $forecast): array
    {
        $items = $forecast->items;
        $inflows = $items->where('type', 'inflow')->sum('weighted_amount');
        $outflows = $items->where('type', 'outflow')->sum('weighted_amount');
        $opening = (float) $forecast->opening_balance;
        $closing = (float) $forecast->projected_closing_balance;

        $avgDailyOutflow = $this->avgDailyOutflow($items, $forecast->base_date, $forecast->end_date);
        $runwayDays = $avgDailyOutflow > 0 ? (int) floor($closing / $avgDailyOutflow) : null;

        return [
            'opening_balance' => round($opening, 2),
            'projected_closing_balance' => round($closing, 2),
            'total_inflows' => round((float) $inflows, 2),
            'total_outflows' => round((float) $outflows, 2),
            'net_cash_flow' => round((float) $inflows - (float) $outflows, 2),
            'runway_days' => $runwayDays,
            'is_positive' => $closing > 0,
            'breaches_minimum' => $forecast->isBreachingMinimum(),
            'minimum_threshold' => $forecast->minimum_balance_threshold !== null
                                         ? round((float) $forecast->minimum_balance_threshold, 2)
                                         : null,
            'period' => [
                'from' => $forecast->base_date->toDateString(),
                'to' => $forecast->end_date->toDateString(),
                'days' => $forecast->base_date->diffInDays($forecast->end_date),
            ],
        ];
    }

    /**
     * Run multiple scenarios (base, optimistic, pessimistic) and compare.
     */
    public function compareScenarios(array $baseAttributes, int $userId): array
    {
        $scenarios = [];

        foreach (['base', 'optimistic', 'pessimistic'] as $scenario) {
            $attrs = $baseAttributes;
            $attrs['scenario'] = $scenario;
            $attrs['name'] = "{$baseAttributes['name']} — {$scenario}";

            $forecast = $this->generate($attrs, $userId);
            $scenarios[$scenario] = $this->buildSummary($forecast);
            $scenarios[$scenario]['forecast_id'] = $forecast->id;
        }

        return $scenarios;
    }

    /**
     * Evaluate all active treasury alerts against the current forecast.
     */
    public function evaluateAlerts(CashFlowForecast $forecast): array
    {
        $alerts = TreasuryAlert::where('is_active', true)->get();
        $triggered = [];

        $timeline = $this->buildTimeline($forecast);

        foreach ($alerts as $alert) {
            foreach ($timeline as $day) {
                $balance = $day['running_balance'];
                $daysOut = (int) now()->diffInDays(Carbon::parse($day['date']), false);

                if ($alert->isTriggered($balance, abs($daysOut))) {
                    $triggered[] = [
                        'alert' => $alert,
                        'date' => $day['date'],
                        'balance' => $balance,
                    ];

                    $alert->update(['last_triggered_at' => now()]);
                    break;
                }
            }
        }

        return $triggered;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function resolveEndDate(Carbon $base, string $horizon): Carbon
    {
        return match ($horizon) {
            '30d' => $base->copy()->addDays(30),
            '60d' => $base->copy()->addDays(60),
            '90d' => $base->copy()->addDays(90),
            default => $base->copy()->addDays(90),
        };
    }

    private function getCurrentCashBalance(): float
    {
        // Pull from cash/bank accounts in the chart of accounts
        $cashIds = ChartOfAccount::where('type', 'asset')
            ->where(function ($q) {
                $q->where('sub_type', 'cash')
                    ->orWhere('sub_type', 'bank')
                    ->orWhereRaw("LOWER(name) LIKE '%cash%'")
                    ->orWhereRaw("LOWER(name) LIKE '%bank%'");
            })
            ->pluck('id');

        if ($cashIds->isEmpty()) {
            return 0;
        }

        $postedEntryIds = DB::table('acc_journal_entries')
            ->where('status', 'posted')
            ->pluck('id');

        $debit = DB::table('acc_journal_entry_lines')
            ->whereIn('account_id', $cashIds)
            ->whereIn('entry_id', $postedEntryIds)
            ->sum('debit');

        $credit = DB::table('acc_journal_entry_lines')
            ->whereIn('account_id', $cashIds)
            ->whereIn('entry_id', $postedEntryIds)
            ->sum('credit');

        return (float) ($debit - $credit);
    }

    private function populateFromReceivables(
        CashFlowForecast $forecast,
        Carbon $from,
        Carbon $to
    ): void {
        $multiplier = $this->scenarioMultiplier($forecast->scenario, 'inflow');

        Invoice::where('type', 'invoice')
            ->whereIn('status', ['posted', 'partial'])
            ->whereBetween('due_date', [$from, $to])
            ->each(function (Invoice $invoice) use ($forecast, $multiplier) {
                $outstanding = (float) $invoice->total - (float) $invoice->amount_paid;
                if ($outstanding <= 0) {
                    return;
                }

                $probability = $this->invoiceProbability($invoice) * $multiplier;

                CashFlowForecastItem::create([
                    'forecast_id' => $forecast->id,
                    'date' => $invoice->due_date,
                    'category' => 'accounts_receivable',
                    'type' => 'inflow',
                    'source' => "Invoice #{$invoice->number}",
                    'description' => 'Customer payment due',
                    'amount' => $outstanding,
                    'probability' => min(100, $probability * 100),
                    'weighted_amount' => $outstanding * min(1, $probability),
                    'reference_type' => Invoice::class,
                    'reference_id' => $invoice->id,
                ]);
            });
    }

    private function populateFromPayables(
        CashFlowForecast $forecast,
        Carbon $from,
        Carbon $to
    ): void {
        $multiplier = $this->scenarioMultiplier($forecast->scenario, 'outflow');

        Invoice::where('type', 'bill')
            ->whereIn('status', ['posted', 'partial'])
            ->whereBetween('due_date', [$from, $to])
            ->each(function (Invoice $invoice) use ($forecast, $multiplier) {
                $outstanding = (float) $invoice->total - (float) $invoice->amount_paid;
                if ($outstanding <= 0) {
                    return;
                }

                CashFlowForecastItem::create([
                    'forecast_id' => $forecast->id,
                    'date' => $invoice->due_date,
                    'category' => 'accounts_payable',
                    'type' => 'outflow',
                    'source' => "Bill #{$invoice->number}",
                    'description' => 'Supplier payment due',
                    'amount' => $outstanding,
                    'probability' => 100,
                    'weighted_amount' => $outstanding * min(1.5, $multiplier),
                    'reference_type' => Invoice::class,
                    'reference_id' => $invoice->id,
                ]);
            });
    }

    private function populateFromRecurring(
        CashFlowForecast $forecast,
        Carbon $from,
        Carbon $to,
        string $scenario
    ): void {
        // Pull last 3 months of salary/payroll outflows as a recurring estimate
        $threeMonthsAgo = $from->copy()->subMonths(3);

        $avgMonthlyPayroll = DB::table('acc_journal_entry_lines as jel')
            ->join('acc_journal_entries as je', 'jel.entry_id', '=', 'je.id')
            ->join('acc_chart_of_accounts as coa', 'jel.account_id', '=', 'coa.id')
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$threeMonthsAgo, $from])
            ->where(fn ($q) => $q
                ->whereRaw("LOWER(coa.name) LIKE '%payroll%'")
                ->orWhereRaw("LOWER(coa.name) LIKE '%salary%'")
                ->orWhereRaw("LOWER(coa.name) LIKE '%wage%'")
            )
            ->avg('jel.debit') ?? 0;

        if ($avgMonthlyPayroll > 0) {
            $multiplier = $this->scenarioMultiplier($scenario, 'outflow');
            $current = $from->copy()->startOfMonth()->addMonth();

            while ($current->lte($to)) {
                CashFlowForecastItem::create([
                    'forecast_id' => $forecast->id,
                    'date' => $current->copy()->endOfMonth(),
                    'category' => 'payroll',
                    'type' => 'outflow',
                    'source' => 'Payroll (estimated)',
                    'description' => 'Monthly payroll based on 3-month average',
                    'amount' => $avgMonthlyPayroll * $multiplier,
                    'probability' => 95,
                    'weighted_amount' => $avgMonthlyPayroll * $multiplier * 0.95,
                ]);

                $current->addMonth();
            }
        }
    }

    private function invoiceProbability(Invoice $invoice): float
    {
        $daysOverdue = now()->diffInDays($invoice->due_date, false);

        if ($daysOverdue >= 0) {
            return 0.90; // Not yet due — high probability
        }
        if ($daysOverdue >= -30) {
            return 0.70; // 1–30 days overdue
        }
        if ($daysOverdue >= -60) {
            return 0.50; // 31–60 days overdue
        }
        if ($daysOverdue >= -90) {
            return 0.30; // 61–90 days overdue
        }

        return 0.10; // 90+ days overdue — unlikely to collect
    }

    private function scenarioMultiplier(string $scenario, string $direction): float
    {
        return match ([$scenario, $direction]) {
            ['optimistic', 'inflow'] => 1.15,
            ['optimistic', 'outflow'] => 0.90,
            ['pessimistic', 'inflow'] => 0.80,
            ['pessimistic', 'outflow'] => 1.10,
            default => 1.00,
        };
    }

    /** @param Collection<int, mixed> $items */
    private function avgDailyOutflow(Collection $items, mixed $from, mixed $to): float
    {
        $totalOutflows = $items->where('type', 'outflow')->sum('weighted_amount');
        $days = max(1, Carbon::parse($from)->diffInDays(Carbon::parse($to)));

        return (float) $totalOutflows / $days;
    }
}
