<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Accounting\Models\CashFlowLine;
use Modules\Accounting\Models\TreasuryForecast;
use Modules\Accounting\Models\TreasuryScenario;

class TreasuryService
{
    /**
     * Create a forecast with optional lines.
     */
    public function createForecast(array $data, array $lines = []): TreasuryForecast
    {
        $forecast = TreasuryForecast::create($data);

        foreach ($lines as $lineData) {
            $this->addLine($forecast, $lineData);
        }

        if (! empty($lines)) {
            $forecast->recompute();
            $forecast->refresh();
        }

        return $forecast;
    }

    /**
     * Add a line to the forecast and recompute totals.
     */
    public function addLine(TreasuryForecast $forecast, array $data): CashFlowLine
    {
        $line = $forecast->lines()->create($data);
        $forecast->recompute();
        $forecast->refresh();

        return $line;
    }

    /**
     * Remove a line and recompute totals.
     */
    public function removeLine(CashFlowLine $line): void
    {
        $forecast = $line->forecast;
        $line->delete();
        $forecast->recompute();
    }

    /**
     * Recompute forecast totals.
     */
    public function recompute(TreasuryForecast $forecast): TreasuryForecast
    {
        $forecast->recompute();

        return $forecast->refresh();
    }

    /**
     * Create a scenario and compute its balance.
     */
    public function createScenario(TreasuryForecast $forecast, array $data): TreasuryScenario
    {
        $scenario = $forecast->scenarios()->create($data);

        $balance = $scenario->computeBalance($forecast);
        $scenario->update(['scenario_balance' => $balance]);

        return $scenario->refresh();
    }

    /**
     * Run scenario analysis: return all 3 scenario types with computed balances.
     * If scenarios don't exist, create defaults.
     */
    public function runScenarioAnalysis(TreasuryForecast $forecast): array
    {
        $defaults = [
            'base' => ['name' => 'Base Scenario',        'type' => 'base',        'adjustment_factor' => 1.0],
            'optimistic' => ['name' => 'Optimistic Scenario',  'type' => 'optimistic',  'adjustment_factor' => 1.2],
            'pessimistic' => ['name' => 'Pessimistic Scenario', 'type' => 'pessimistic', 'adjustment_factor' => 0.8],
        ];

        $results = [];

        foreach ($defaults as $type => $defaultData) {
            $scenario = $forecast->scenarios()->where('type', $type)->first();

            if (! $scenario) {
                $scenario = $this->createScenario($forecast, $defaultData);
            } else {
                $balance = $scenario->computeBalance($forecast);
                $scenario->update(['scenario_balance' => $balance]);
                $scenario->refresh();
            }

            $results[$type] = $scenario;
        }

        return $results;
    }

    /**
     * Get 12-month rolling cash flow projection.
     */
    public function getMonthlyProjection(float $openingBalance = 0.0, int $months = 12): array
    {
        $projection = [];
        $cumulativeBalance = $openingBalance;
        $now = Carbon::now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = (clone $now)->addMonths($i);
            $monthEnd = (clone $monthStart)->endOfMonth();
            $monthKey = $monthStart->format('Y-m');

            $inflows = (float) CashFlowLine::where('flow_type', 'inflow')
                ->whereBetween('expected_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount');

            $outflows = (float) CashFlowLine::where('flow_type', 'outflow')
                ->whereBetween('expected_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount');

            $net = $inflows - $outflows;
            $cumulativeBalance += $net;

            $projection[] = [
                'month' => $monthKey,
                'inflows' => $inflows,
                'outflows' => $outflows,
                'net' => $net,
                'cumulative_balance' => $cumulativeBalance,
            ];
        }

        return $projection;
    }

    /**
     * Get forecasts overlapping with the given date range.
     */
    public function getForecastsInRange(Carbon $from, Carbon $to): Collection
    {
        return TreasuryForecast::where('period_start', '<=', $to->toDateString())
            ->where('period_end', '>=', $from->toDateString())
            ->get();
    }

    /**
     * Get treasury dashboard summary.
     */
    public function getDashboard(): array
    {
        $latestActive = TreasuryForecast::where('status', 'active')
            ->orderByDesc('created_at')
            ->first();

        $currentBalance = $latestActive ? (float) $latestActive->opening_balance : 0.0;

        // Monthly burn rate: avg outflows from last 3 months of lines
        $threeMonthsAgo = Carbon::now()->subMonths(3)->startOfMonth();
        $totalOutflows = (float) CashFlowLine::where('flow_type', 'outflow')
            ->where('expected_date', '>=', $threeMonthsAgo->toDateString())
            ->sum('amount');
        $monthlyBurnRate = $totalOutflows / 3;

        $runwayMonths = ($monthlyBurnRate > 0) ? $currentBalance / $monthlyBurnRate : 0.0;

        $totalForecastedInflows = (float) TreasuryForecast::where('status', 'active')->sum('total_inflows');
        $totalForecastedOutflows = (float) TreasuryForecast::where('status', 'active')->sum('total_outflows');

        return [
            'current_balance' => $currentBalance,
            'monthly_burn_rate' => $monthlyBurnRate,
            'runway_months' => $runwayMonths,
            'total_forecasted_inflows' => $totalForecastedInflows,
            'total_forecasted_outflows' => $totalForecastedOutflows,
        ];
    }

    /**
     * Mark a line as realized with an actual amount.
     */
    public function realizeLine(CashFlowLine $line, float $actualAmount): CashFlowLine
    {
        $line->update(['actual_amount' => $actualAmount]);

        return $line->refresh();
    }
}
