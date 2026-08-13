<?php

declare(strict_types=1);

namespace Modules\Strategy\Services;

use Modules\Strategy\Models\IndustryBenchmark;

/**
 * Retrieves country + industry benchmark data and computes percentile positions.
 */
class BenchmarkService
{
    /**
     * Built-in benchmark data for African / global SME contexts.
     * Used as fallback when no IndustryBenchmark rows exist in DB.
     */
    private const DEFAULTS = [
        'current_ratio'           => ['p25' => 1.1, 'median' => 1.5, 'p75' => 2.2],
        'debt_to_equity'          => ['p25' => 0.3, 'median' => 0.7, 'p75' => 1.4],
        'net_profit_margin'       => ['p25' => 5.0, 'median' => 10.0, 'p75' => 18.0],
        'ebitda_margin'           => ['p25' => 8.0, 'median' => 15.0, 'p75' => 25.0],
        'dso'                     => ['p25' => 28.0, 'median' => 40.0, 'p75' => 60.0],
        'dpo'                     => ['p25' => 25.0, 'median' => 38.0, 'p75' => 55.0],
        'lead_conversion_rate'    => ['p25' => 10.0, 'median' => 18.0, 'p75' => 30.0],
        'cac'                     => ['p25' => 50000.0, 'median' => 90000.0, 'p75' => 180000.0],
        'clv'                     => ['p25' => 300000.0, 'median' => 800000.0, 'p75' => 2000000.0],
        'win_rate'                => ['p25' => 20.0, 'median' => 30.0, 'p75' => 45.0],
        'pipeline_velocity'       => ['p25' => 1000000.0, 'median' => 2500000.0, 'p75' => 6000000.0],
        'turnover_rate'           => ['p25' => 8.0, 'median' => 14.0, 'p75' => 22.0],
        'absenteeism_rate'        => ['p25' => 2.0, 'median' => 4.0, 'p75' => 7.0],
        'revenue_per_employee'    => ['p25' => 3000000.0, 'median' => 6500000.0, 'p75' => 14000000.0],
        'training_roi'            => ['p25' => 50.0, 'median' => 120.0, 'p75' => 250.0],
        'time_to_fill'            => ['p25' => 20.0, 'median' => 32.0, 'p75' => 55.0],
        'inventory_turnover'      => ['p25' => 3.0, 'median' => 5.5, 'p75' => 9.0],
        'stockout_rate'           => ['p25' => 1.0, 'median' => 3.0, 'p75' => 6.0],
        'fill_rate'               => ['p25' => 88.0, 'median' => 94.0, 'p75' => 98.0],
        'carrying_cost_ratio'     => ['p25' => 12.0, 'median' => 20.0, 'p75' => 30.0],
        'revenue_growth_rate'     => ['p25' => 5.0, 'median' => 12.0, 'p75' => 25.0],
        'avg_deal_size'           => ['p25' => 150000.0, 'median' => 350000.0, 'p75' => 800000.0],
        'sales_cycle_length'      => ['p25' => 18.0, 'median' => 35.0, 'p75' => 65.0],
        'quota_attainment'        => ['p25' => 65.0, 'median' => 82.0, 'p75' => 100.0],
        'oee'                     => ['p25' => 55.0, 'median' => 72.0, 'p75' => 85.0],
        'defect_rate'             => ['p25' => 0.5, 'median' => 1.8, 'p75' => 4.0],
        'production_efficiency'   => ['p25' => 68.0, 'median' => 80.0, 'p75' => 92.0],
        'scrap_rate'              => ['p25' => 1.0, 'median' => 2.5, 'p75' => 5.0],
        'first_response_time'     => ['p25' => 5.0, 'median' => 14.0, 'p75' => 30.0],
        'resolution_rate'         => ['p25' => 78.0, 'median' => 88.0, 'p75' => 96.0],
        'csat_score'              => ['p25' => 3.5, 'median' => 4.1, 'p75' => 4.7],
        'escalation_rate'         => ['p25' => 3.0, 'median' => 8.0, 'p75' => 15.0],
    ];

    /**
     * Get benchmark data for a ratio key (DB first, fallback to defaults).
     *
     * @return array{p25: float|null, median: float|null, p75: float|null, source: string}
     */
    public function getForRatio(string $ratioKey, string $tenantId = 'default', string $country = 'WW', string $industry = 'general'): array
    {
        // Try DB first
        $dbRecord = IndustryBenchmark::where('ratio_name', $ratioKey)
            ->where(function ($q) use ($country, $industry) {
                $q->where(['country' => $country, 'industry' => $industry])
                  ->orWhere(['country' => 'WW', 'industry' => 'general']);
            })
            ->orderByRaw("CASE WHEN country = ? AND industry = ? THEN 0 ELSE 1 END", [$country, $industry])
            ->first();

        if ($dbRecord) {
            return [
                'p25'    => (float) $dbRecord->p25,
                'median' => (float) $dbRecord->median,
                'p75'    => (float) $dbRecord->p75,
                'source' => $dbRecord->source ?? 'DB',
            ];
        }

        // Fallback to built-in defaults
        $defaults = self::DEFAULTS[$ratioKey] ?? [];
        return [
            'p25'    => $defaults['p25'] ?? null,
            'median' => $defaults['median'] ?? null,
            'p75'    => $defaults['p75'] ?? null,
            'source' => 'WideHalo Global Benchmark 2026',
        ];
    }

    /**
     * List all benchmark data from DB, optionally filtered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAll(?string $country = null, ?string $industry = null, ?int $year = null): array
    {
        $query = IndustryBenchmark::query();
        if ($country)  $query->where('country', $country);
        if ($industry) $query->where('industry', $industry);
        if ($year)     $query->where('year', $year);

        $dbRows = $query->orderBy('ratio_name')->get()->toArray();

        // If DB is empty, return the built-in defaults as structured rows
        if (empty($dbRows)) {
            return $this->defaultsAsRows();
        }

        return $dbRows;
    }

    /**
     * Compute the percentile rank of a value in a benchmark distribution.
     */
    public function percentileRank(float $value, array $benchmarkData, string $direction = 'up'): ?int
    {
        $p25 = $benchmarkData['p25'] ?? null;
        $p75 = $benchmarkData['p75'] ?? null;

        if ($p25 === null || $p75 === null) return null;

        if ($direction === 'up') {
            if ($value <= $p25) return 25;
            if ($value >= $p75) return 75;
            return (int) round(($value - $p25) / ($p75 - $p25) * 50 + 25);
        }

        // For "down" metrics, high value = low percentile rank
        if ($value >= $p75) return 25;
        if ($value <= $p25) return 75;
        return (int) round(($p75 - $value) / ($p75 - $p25) * 50 + 25);
    }

    private function defaultsAsRows(): array
    {
        $rows = [];
        foreach (self::DEFAULTS as $ratioName => $data) {
            $rows[] = [
                'id'         => null,
                'ratio_name' => $ratioName,
                'industry'   => 'general',
                'country'    => 'WW',
                'p25'        => $data['p25'],
                'median'     => $data['median'],
                'p75'        => $data['p75'],
                'year'       => 2026,
                'source'     => 'WideHalo Global Benchmark 2026',
            ];
        }
        return $rows;
    }
}
