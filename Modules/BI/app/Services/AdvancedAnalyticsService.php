<?php

namespace Modules\BI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class AdvancedAnalyticsService
{
    const CACHE_TTL = 3600;

    /**
     * Calculate trend analysis
     */
    public function analyzeTrend(array $data, string $dateColumn, string $valueColumn): array
    {
        if (empty($data)) {
            return ['error' => 'No data provided'];
        }

        // Sort by date
        usort($data, function ($a, $b) {
            return strtotime($a[$dateColumn]) - strtotime($b[$dateColumn]);
        });

        $values = array_map(fn($row) => (float)$row[$valueColumn], $data);
        $count = count($values);

        return [
            'trend' => $this->calculateLinearTrend($values),
            'volatility' => $this->calculateVolatility($values),
            'momentum' => $this->calculateMomentum($values),
            'seasonality' => $this->detectSeasonality($data, $dateColumn, $valueColumn),
            'anomalies' => $this->detectAnomalies($values),
            'forecast' => $this->forecastValues($values, 5),
        ];
    }

    /**
     * Calculate linear trend
     */
    private function calculateLinearTrend(array $values): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0, 'direction' => 'flat'];
        }

        $sumX = $n * ($n - 1) / 2;
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        return [
            'slope' => round($slope, 4),
            'direction' => $slope > 0 ? 'upward' : ($slope < 0 ? 'downward' : 'flat'),
            'strength' => abs($slope),
        ];
    }

    /**
     * Calculate volatility (standard deviation)
     */
    private function calculateVolatility(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn($v) => pow($v - $mean, 2), $values);
        $variance = array_sum($squaredDiffs) / count($squaredDiffs);

        return round(sqrt($variance), 4);
    }

    /**
     * Calculate momentum (rate of change)
     */
    private function calculateMomentum(array $values): array
    {
        $momentum = [];
        $period = min(5, count($values) - 1);

        if (count($values) < $period + 1) {
            return ['momentum' => 0, 'strength' => 'low'];
        }

        for ($i = $period; $i < count($values); $i++) {
            $momentum[] = (($values[$i] - $values[$i - $period]) / $values[$i - $period]) * 100;
        }

        $avg = array_sum($momentum) / count($momentum);

        return [
            'momentum' => round($avg, 2),
            'strength' => abs($avg) > 10 ? 'strong' : (abs($avg) > 5 ? 'moderate' : 'weak'),
        ];
    }

    /**
     * Detect seasonality patterns
     */
    private function detectSeasonality(array $data, string $dateColumn, string $valueColumn): array
    {
        $monthlyValues = [];

        foreach ($data as $row) {
            $month = date('m', strtotime($row[$dateColumn]));
            if (!isset($monthlyValues[$month])) {
                $monthlyValues[$month] = [];
            }
            $monthlyValues[$month][] = (float)$row[$valueColumn];
        }

        $seasonality = [];
        foreach ($monthlyValues as $month => $values) {
            $seasonality[$month] = [
                'average' => round(array_sum($values) / count($values), 2),
                'count' => count($values),
            ];
        }

        return [
            'pattern' => $seasonality,
            'detected' => count($seasonality) > 6,
        ];
    }

    /**
     * Detect anomalies using z-score
     */
    private function detectAnomalies(array $values): array
    {
        $mean = array_sum($values) / count($values);
        $stdDev = $this->calculateVolatility($values);

        if ($stdDev === 0) {
            return [];
        }

        $anomalies = [];

        foreach ($values as $index => $value) {
            $zScore = abs(($value - $mean) / $stdDev);

            if ($zScore > 3) { // 3 sigma rule
                $anomalies[] = [
                    'index' => $index,
                    'value' => $value,
                    'z_score' => round($zScore, 2),
                    'severity' => $zScore > 4 ? 'high' : 'medium',
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Forecast future values
     */
    private function forecastValues(array $values, int $periods = 5): array
    {
        $trend = $this->calculateLinearTrend($values);
        $lastValue = end($values);
        $forecast = [];

        for ($i = 1; $i <= $periods; $i++) {
            $forecast[] = [
                'period' => $i,
                'value' => round($lastValue + ($trend['slope'] * $i), 2),
                'confidence' => 0.95 - ($i * 0.05),
            ];
        }

        return $forecast;
    }

    /**
     * Cohort analysis
     */
    public function analyzeCohorts(array $data, string $groupColumn, string $dateColumn, string $metricColumn): array
    {
        $cohorts = [];

        foreach ($data as $row) {
            $group = $row[$groupColumn];
            $period = date('Y-m', strtotime($row[$dateColumn]));

            if (!isset($cohorts[$group])) {
                $cohorts[$group] = [];
            }

            if (!isset($cohorts[$group][$period])) {
                $cohorts[$group][$period] = [];
            }

            $cohorts[$group][$period][] = (float)$row[$metricColumn];
        }

        // Calculate metrics for each cohort
        $analysis = [];

        foreach ($cohorts as $group => $periods) {
            $analysis[$group] = [];

            foreach ($periods as $period => $values) {
                $analysis[$group][$period] = [
                    'count' => count($values),
                    'sum' => round(array_sum($values), 2),
                    'average' => round(array_sum($values) / count($values), 2),
                    'min' => round(min($values), 2),
                    'max' => round(max($values), 2),
                ];
            }
        }

        return $analysis;
    }

    /**
     * Cohort retention analysis
     */
    public function analyzeRetention(array $data, string $userColumn, string $dateColumn): array
    {
        $retention = [];

        foreach ($data as $row) {
            $user = $row[$userColumn];
            $date = date('Y-m-d', strtotime($row[$dateColumn]));

            if (!isset($retention[$user])) {
                $retention[$user] = [];
            }

            $retention[$user][] = $date;
        }

        // Calculate retention rates
        $analysis = [];

        foreach ($retention as $user => $dates) {
            sort($dates);
            $firstDate = strtotime($dates[0]);
            $retained = 0;

            for ($i = 1; $i < count($dates); $i++) {
                $daysElapsed = (strtotime($dates[$i]) - $firstDate) / (24 * 3600);

                if ($daysElapsed <= 30) {
                    $retained++;
                }
            }

            $analysis[$user] = [
                'first_activity' => $dates[0],
                'last_activity' => end($dates),
                'activities' => count($dates),
                'retention_30d' => round(($retained / max(count($dates) - 1, 1)) * 100, 2),
            ];
        }

        return $analysis;
    }

    /**
     * Customer lifetime value estimation
     */
    public function estimateLTV(array $transactions, string $userColumn, string $amountColumn, int $months = 12): array
    {
        $userMetrics = [];

        foreach ($transactions as $transaction) {
            $user = $transaction[$userColumn];
            $amount = (float)$transaction[$amountColumn];

            if (!isset($userMetrics[$user])) {
                $userMetrics[$user] = ['total' => 0, 'count' => 0];
            }

            $userMetrics[$user]['total'] += $amount;
            $userMetrics[$user]['count']++;
        }

        $ltv = [];

        foreach ($userMetrics as $user => $metrics) {
            $avgTransaction = $metrics['total'] / $metrics['count'];
            $monthlyAvg = $metrics['count'] / $months;

            $ltv[$user] = [
                'total_spent' => round($metrics['total'], 2),
                'transaction_count' => $metrics['count'],
                'avg_transaction' => round($avgTransaction, 2),
                'estimated_ltv' => round($avgTransaction * $monthlyAvg * 24, 2), // 24 months projection
            ];
        }

        return $ltv;
    }
}
