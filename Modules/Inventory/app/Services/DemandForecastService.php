<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Inventory\Models\DemandForecast;
use Modules\Inventory\Models\SeasonalFactor;
use Modules\Inventory\Models\StockMovement;

class DemandForecastService
{
    private const MOVING_AVERAGE_PERIODS = 3;

    /**
     * Generate monthly demand forecasts for the next N months using historical outgoing stock movements.
     *
     * @param  int  $months  Number of future months to forecast
     * @param  string  $method  moving_average | exponential_smoothing | seasonal
     * @return Collection<int, DemandForecast>
     */
    public function generate(int $productId, ?int $warehouseId, int $months = 3, string $method = 'moving_average'): Collection
    {
        $history = $this->historicalMonthlyDemand($productId, $warehouseId);
        $forecasts = collect();

        for ($i = 1; $i <= $months; $i++) {
            $periodStart = Carbon::now()->startOfMonth()->addMonths($i);
            $periodEnd = $periodStart->copy()->endOfMonth();

            $base = match ($method) {
                'exponential_smoothing' => $this->exponentialSmoothing($history),
                'seasonal' => $this->seasonalForecast($productId, $history, $periodStart->month),
                default => $this->movingAverage($history),
            };

            $seasonal = SeasonalFactor::where('product_id', $productId)
                ->where('period_type', 'monthly')
                ->where('period_index', $periodStart->month)
                ->value('factor') ?? 1.0;

            $qty = max(0.0, round($base * $seasonal, 4));

            $existing = DemandForecast::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->whereDate('period_start', $periodStart)
                ->first();

            $forecast = DemandForecast::updateOrCreate(
                [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'period_start' => $periodStart,
                ],
                [
                    'period_end' => $periodEnd,
                    'period_type' => 'monthly',
                    'forecasted_qty' => $qty,
                    'method' => $method,
                    'confidence' => $this->confidence($history),
                    'status' => $existing?->status === 'confirmed' ? 'confirmed' : 'draft',
                    'metadata' => ['historical_periods' => $history->count(), 'seasonal_factor' => $seasonal],
                ]
            );

            $forecasts->push($forecast);
        }

        return $forecasts;
    }

    /**
     * Reconcile forecasts with actual demand for the given product/month.
     */
    public function reconcile(int $productId, ?int $warehouseId, Carbon $month): ?DemandForecast
    {
        $forecast = DemandForecast::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereDate('period_start', $month->startOfMonth())
            ->first();

        if (! $forecast) {
            return null;
        }

        $actual = $this->historicalMonthlyDemand($productId, $warehouseId, 1, $month)->first() ?? 0.0;
        $forecast->update(['actual_qty' => $actual, 'status' => 'expired']);

        return $forecast->fresh();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function historicalMonthlyDemand(int $productId, ?int $warehouseId, int $periods = 12, ?Carbon $before = null): Collection
    {
        $before ??= Carbon::now()->startOfMonth();

        $query = StockMovement::where('product_id', $productId)
            ->where('type', 'out')
            ->where('created_at', '<', $before);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $isMySQL = in_array(config('database.default'), ['mysql', 'mariadb']);
        $monthExpr = $isMySQL
            ? "DATE_FORMAT(created_at, '%Y-%m-01')"
            : "strftime('%Y-%m-01', created_at)";

        return $query
            ->selectRaw("{$monthExpr} as month, SUM(quantity) as qty")
            ->groupByRaw($monthExpr)
            ->orderByDesc('month')
            ->limit($periods)
            ->pluck('qty')
            ->map(fn ($v) => (float) $v);
    }

    /** @param Collection<int,float> $history */
    private function movingAverage(Collection $history): float
    {
        $slice = $history->take(self::MOVING_AVERAGE_PERIODS);

        return $slice->isEmpty() ? 0.0 : $slice->average();
    }

    /** @param Collection<int,float> $history */
    private function exponentialSmoothing(Collection $history, float $alpha = 0.3): float
    {
        if ($history->isEmpty()) {
            return 0.0;
        }
        $smoothed = $history->last();
        foreach ($history->reverse()->skip(1) as $value) {
            $smoothed = $alpha * $value + (1 - $alpha) * $smoothed;
        }

        return round($smoothed, 4);
    }

    /** @param Collection<int,float> $history */
    private function seasonalForecast(int $productId, Collection $history, int $month): float
    {
        $avg = $history->average() ?: 0.0;
        $factor = SeasonalFactor::where('product_id', $productId)
            ->where('period_type', 'monthly')
            ->where('period_index', $month)
            ->value('factor') ?? 1.0;

        return round($avg * $factor, 4);
    }

    /** @param Collection<int,float> $history */
    private function confidence(Collection $history): float
    {
        if ($history->count() < 2) {
            return 50.0;
        }
        $avg = $history->average();
        if ($avg == 0) {
            return 50.0;
        }
        $variance = $history->sum(fn ($v) => ($v - $avg) ** 2) / $history->count();
        $cv = sqrt($variance) / $avg; // coefficient of variation

        return round(max(0.0, min(100.0, 100 - $cv * 100)), 2);
    }
}
