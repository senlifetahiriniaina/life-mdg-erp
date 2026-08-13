<?php

declare(strict_types=1);

namespace Modules\Shared\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnifiedForecastingService extends BaseService
{
    private const CACHE_TTL = 86400; // 24 hours
    private const MIN_HISTORICAL_DAYS = 30;
    private const CONFIDENCE_THRESHOLD = 0.7;

    protected string $table;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
    }

    /**
     * Generate forecast using ARIMA
     */
    public function forecastARIMA(
        array $historicalData,
        int $daysAhead = 30,
        array $arimaParams = ['p' => 1, 'q' => 1]
    ): Collection {
        try {
            if (count($historicalData) < self::MIN_HISTORICAL_DAYS) {
                Log::warning('[Forecasting] Insufficient historical data for ARIMA');
                return collect();
            }

            $values = array_values($historicalData);
            $forecasts = [];

            // Simplified ARIMA implementation using exponential smoothing
            for ($i = 0; $i < $daysAhead; $i++) {
                $forecast = $this->arimaPredict($values, $arimaParams);
                $forecasts[] = [
                    'value' => $forecast,
                    'confidence' => 0.75,
                    'model' => 'arima',
                    'days_ahead' => $i + 1,
                ];
            }

            return collect($forecasts);
        } catch (\Throwable $e) {
            Log::error('[Forecasting] ARIMA forecast failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Generate forecast using exponential smoothing
     */
    public function forecastExponentialSmoothing(
        array $historicalData,
        int $daysAhead = 30,
        float $alpha = 0.3
    ): Collection {
        try {
            if (count($historicalData) < self::MIN_HISTORICAL_DAYS) {
                Log::warning('[Forecasting] Insufficient historical data for exponential smoothing');
                return collect();
            }

            $values = array_values($historicalData);
            $forecasts = [];
            $level = end($values);

            for ($i = 0; $i < $daysAhead; $i++) {
                $forecast = $level;
                // Apply exponential smoothing
                foreach (array_slice(array_reverse($values), 0, 3) as $idx => $value) {
                    $weight = pow(1 - $alpha, $idx);
                    $forecast = $alpha * $value + (1 - $alpha) * $forecast;
                }

                $forecasts[] = [
                    'value' => round($forecast, 2),
                    'confidence' => 0.80,
                    'model' => 'exponential_smoothing',
                    'days_ahead' => $i + 1,
                ];

                $level = $forecast;
            }

            return collect($forecasts);
        } catch (\Throwable $e) {
            Log::error('[Forecasting] Exponential smoothing forecast failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Generate ensemble forecast combining multiple models
     */
    public function forecastEnsemble(
        array $historicalData,
        int $daysAhead = 30
    ): Collection {
        try {
            $arimaPredictions = $this->forecastARIMA($historicalData, $daysAhead);
            $smoothingPredictions = $this->forecastExponentialSmoothing($historicalData, $daysAhead);

            $forecasts = [];
            for ($i = 0; $i < $daysAhead; $i++) {
                $arimaBased = $arimaPredictions[$i] ?? null;
                $smoothingBased = $smoothingPredictions[$i] ?? null;

                if ($arimaBased && $smoothingBased) {
                    $averageValue = ($arimaBased['value'] + $smoothingBased['value']) / 2;
                    $confidence = (($arimaBased['confidence'] + $smoothingBased['confidence']) / 2);

                    $forecasts[] = [
                        'value' => round($averageValue, 2),
                        'confidence' => round($confidence, 2),
                        'model' => 'ensemble',
                        'days_ahead' => $i + 1,
                        'components' => [
                            'arima' => $arimaBased['value'],
                            'exponential_smoothing' => $smoothingBased['value'],
                        ],
                    ];
                }
            }

            return collect($forecasts);
        } catch (\Throwable $e) {
            Log::error('[Forecasting] Ensemble forecast failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Detect seasonality in data
     */
    public function detectSeasonality(array $historicalData, int $period = 7): array
    {
        if (count($historicalData) < $period * 2) {
            return [];
        }

        $values = array_values($historicalData);
        $seasonality = [];

        for ($i = 0; $i < $period; $i++) {
            $seasonalValues = [];
            for ($j = $i; $j < count($values); $j += $period) {
                $seasonalValues[] = $values[$j];
            }

            if (!empty($seasonalValues)) {
                $average = array_sum($seasonalValues) / count($seasonalValues);
                $seasonality[$i] = $average > 0 ? $average / array_sum($values) * $period : 0;
            }
        }

        return $seasonality;
    }

    /**
     * Apply seasonality adjustment to forecasts
     */
    public function applySeasonalityAdjustment(Collection $forecasts, array $seasonality): Collection
    {
        if (empty($seasonality)) {
            return $forecasts;
        }

        return $forecasts->map(function ($forecast, $idx) use ($seasonality) {
            $seasonalIndex = $idx % count($seasonality);
            $adjustment = $seasonality[$seasonalIndex] ?? 1.0;
            $forecast['value'] = round($forecast['value'] * $adjustment, 2);
            return $forecast;
        });
    }

    /**
     * Calculate forecast accuracy (MAPE)
     */
    public function calculateAccuracy(array $actual, array $predicted): float
    {
        if (empty($actual) || empty($predicted) || count($actual) !== count($predicted)) {
            return 0.0;
        }

        $errors = [];
        foreach ($actual as $idx => $value) {
            if ($value != 0) {
                $errors[] = abs(($value - $predicted[$idx]) / $value);
            }
        }

        if (empty($errors)) {
            return 0.0;
        }

        $mape = (array_sum($errors) / count($errors)) * 100;
        return round(100 - min($mape, 100), 2);
    }

    /**
     * Get historical data from database
     */
    public function getHistoricalData(
        string $column,
        string $table,
        array $filters = [],
        int $days = 365
    ): array {
        try {
            $query = DB::table($table)
                ->where('company_id', $this->companyId)
                ->where('created_at', '>=', Carbon::now()->subDays($days));

            foreach ($filters as $key => $value) {
                $query->where($key, $value);
            }

            $records = $query
                ->orderBy('created_at', 'asc')
                ->select('created_at', $column)
                ->get()
                ->toArray();

            return array_map(fn ($r) => (float) $r->{$column}, $records);
        } catch (\Throwable $e) {
            Log::error('[Forecasting] Failed to get historical data', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get cached forecast
     */
    public function getCachedForecast(string $cacheKey): ?Collection
    {
        return Cache::get("forecast:{$cacheKey}");
    }

    /**
     * Cache forecast
     */
    public function cacheForecast(string $cacheKey, Collection $forecast, int $ttl = self::CACHE_TTL): void
    {
        Cache::put("forecast:{$cacheKey}", $forecast, $ttl);
    }

    /**
     * Clear forecast cache
     */
    public function clearForecastCache(string $cacheKey): void
    {
        Cache::forget("forecast:{$cacheKey}");
    }

    // ==================== Private Helper Methods ====================

    /**
     * Simple ARIMA prediction using differencing and autoregression
     */
    private function arimaPredict(array $values, array $params): float
    {
        $p = $params['p'] ?? 1;
        $q = $params['q'] ?? 1;

        $lastValues = array_slice($values, -$p);
        $forecast = array_sum($lastValues) / count($lastValues);

        // Add autoregressive component
        if ($p > 0 && count($values) > 1) {
            $differences = [];
            for ($i = 1; $i < count($values); $i++) {
                $differences[] = $values[$i] - $values[$i - 1];
            }

            if (!empty($differences)) {
                $avgDiff = array_sum($differences) / count($differences);
                $forecast += $avgDiff;
            }
        }

        return max(0, round($forecast, 2));
    }
}
