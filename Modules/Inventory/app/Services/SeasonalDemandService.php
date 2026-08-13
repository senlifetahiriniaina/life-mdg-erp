<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Carbon\Carbon;
use Modules\Inventory\Models\DemandForecast;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\SeasonalFactor;

class SeasonalDemandService
{
    public function applySeasonalFactor(DemandForecast $forecast): bool
    {
        $product = $forecast->product;

        $factor = $this->getSeasonalFactor($product, $forecast->period_start);

        if (! $factor) {
            return false;
        }

        $adjusted = $forecast->forecasted_qty * $factor;

        $forecast->update([
            'forecasted_qty' => $adjusted,
            'metadata' => array_merge(
                $forecast->metadata ?? [],
                ['seasonal_factor' => $factor]
            ),
            'status' => 'adjusted',
        ]);

        return true;
    }

    public function adjustForecastWithSeasonal(DemandForecast $forecast): float
    {
        $factor = $this->getSeasonalFactor($forecast->product, $forecast->period_start);

        if (! $factor) {
            return $forecast->forecasted_qty;
        }

        return round($forecast->forecasted_qty * $factor, 2);
    }

    public function getSeasonalFactor(Product $product, Carbon $date): ?float
    {
        $month = $date->month;
        $quarter = ceil($month / 3);

        $factor = SeasonalFactor::query()
            ->where('product_id', $product->id)
            ->where('period_type', 'monthly')
            ->where('period_index', $month)
            ->first();

        if (! $factor) {
            $factor = SeasonalFactor::query()
                ->where('category_id', $product->category_id)
                ->where('period_type', 'monthly')
                ->where('period_index', $month)
                ->first();
        }

        return $factor?->factor;
    }

    public function calculateSeasonalStockTargets(Product $product, float $baseStock): array
    {
        $targets = [];

        for ($month = 1; $month <= 12; $month++) {
            $factor = SeasonalFactor::query()
                ->where('product_id', $product->id)
                ->where('period_type', 'monthly')
                ->where('period_index', $month)
                ->first();

            if (! $factor && $product->category_id) {
                $factor = SeasonalFactor::query()
                    ->where('category_id', $product->category_id)
                    ->where('period_type', 'monthly')
                    ->where('period_index', $month)
                    ->first();
            }

            $multiplier = $factor?->factor ?? 1.0;
            $targets[$month] = round($baseStock * $multiplier, 2);
        }

        return $targets;
    }

    public function forecastDemandWithSeasonal(
        Product $product,
        float $baseDemand,
        string $periodType = 'monthly',
        int $periodCount = 12
    ): array {
        $forecasts = [];

        $now = now();

        for ($i = 0; $i < $periodCount; $i++) {
            $periodStart = $now->copy()->addMonths($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $month = $periodStart->month;

            $seasonalFactor = $this->getSeasonalFactorForPeriod(
                $product,
                $month,
                $periodType
            );

            $adjustedDemand = $baseDemand * $seasonalFactor;

            $forecasts[] = [
                'product_id' => $product->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'base_demand' => $baseDemand,
                'seasonal_factor' => $seasonalFactor,
                'forecasted_qty' => round($adjustedDemand, 2),
                'period_type' => $periodType,
                'method' => 'seasonal_adjusted_historical',
            ];
        }

        return $forecasts;
    }

    private function getSeasonalFactorForPeriod(Product $product, int $month, string $periodType): float
    {
        $factor = SeasonalFactor::query()
            ->where('product_id', $product->id)
            ->where('period_type', $periodType)
            ->where('period_index', $month)
            ->first();

        if (! $factor && $product->category_id) {
            $factor = SeasonalFactor::query()
                ->where('category_id', $product->category_id)
                ->where('period_type', $periodType)
                ->where('period_index', $month)
                ->first();
        }

        return $factor?->factor ?? 1.0;
    }

    public function validateSeasonalFactors(array $factors): array
    {
        $errors = [];

        foreach ($factors as $index => $factor) {
            if (empty($factor['product_id']) && empty($factor['category_id'])) {
                $errors[] = "Factor $index: Must specify either product_id or category_id";
            }

            if (! in_array($factor['period_type'] ?? '', ['monthly', 'quarterly', 'yearly'])) {
                $errors[] = "Factor $index: Invalid period_type";
            }

            $periodIndex = $factor['period_index'] ?? null;
            if (! $periodIndex || $periodIndex < 1 || $periodIndex > 12) {
                $errors[] = "Factor $index: period_index must be between 1 and 12";
            }

            $factorValue = $factor['factor'] ?? null;
            if (! $factorValue || $factorValue < 0.1 || $factorValue > 10) {
                $errors[] = "Factor $index: factor value must be between 0.1 and 10";
            }
        }

        return $errors;
    }
}
