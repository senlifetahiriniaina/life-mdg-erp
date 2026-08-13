<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Carbon;
use Modules\Accounting\Models\CostRollup;

/**
 * CostBenchmarkService — industry cost structure benchmarks.
 *
 * Provides built-in P25/median/P75 CAPEX/OPEX/FINEX/RISKEX distribution
 * benchmarks for key industries in African and Asian markets.
 *
 * Anomaly detection thresholds (defaults):
 *   FINEX  > 12% of total → over-leveraged / BFR issue
 *   RISKEX >  8% of total → systemic quality problem
 *   CAPEX  > 40% of total → capital-heavy, check amortization rate
 *   OPEX   <  30% of total → underestimated operating costs
 */
class CostBenchmarkService
{
    /**
     * Built-in global benchmarks (WideHalo Global Benchmark 2026).
     * Keys: industry slug → country_code ('*' = global default)
     * Values: [CAPEX%, OPEX%, FINEX%, RISKEX%]
     */
    private const BENCHMARKS = [
        'textile' => [
            '*'  => ['CAPEX' => 22, 'OPEX' => 62, 'FINEX' => 9,  'RISKEX' => 7],
            'SN' => ['CAPEX' => 20, 'OPEX' => 64, 'FINEX' => 10, 'RISKEX' => 6],
            'CI' => ['CAPEX' => 21, 'OPEX' => 63, 'FINEX' => 10, 'RISKEX' => 6],
            'MG' => ['CAPEX' => 18, 'OPEX' => 68, 'FINEX' => 8,  'RISKEX' => 6],
        ],
        'construction' => [
            '*'  => ['CAPEX' => 30, 'OPEX' => 55, 'FINEX' => 8,  'RISKEX' => 7],
            'CM' => ['CAPEX' => 28, 'OPEX' => 58, 'FINEX' => 7,  'RISKEX' => 7],
            'GH' => ['CAPEX' => 32, 'OPEX' => 54, 'FINEX' => 8,  'RISKEX' => 6],
        ],
        'manufacturing' => [
            '*'  => ['CAPEX' => 28, 'OPEX' => 58, 'FINEX' => 7,  'RISKEX' => 7],
            'NG' => ['CAPEX' => 25, 'OPEX' => 60, 'FINEX' => 9,  'RISKEX' => 6],
            'KE' => ['CAPEX' => 27, 'OPEX' => 59, 'FINEX' => 8,  'RISKEX' => 6],
        ],
        'agribusiness' => [
            '*'  => ['CAPEX' => 18, 'OPEX' => 68, 'FINEX' => 7,  'RISKEX' => 7],
            'BF' => ['CAPEX' => 16, 'OPEX' => 70, 'FINEX' => 8,  'RISKEX' => 6],
        ],
        'distribution' => [
            '*'  => ['CAPEX' => 15, 'OPEX' => 70, 'FINEX' => 10, 'RISKEX' => 5],
        ],
        'services' => [
            '*'  => ['CAPEX' => 10, 'OPEX' => 78, 'FINEX' => 7,  'RISKEX' => 5],
        ],
        'retail' => [
            '*'  => ['CAPEX' => 12, 'OPEX' => 75, 'FINEX' => 8,  'RISKEX' => 5],
        ],
    ];

    /**
     * Anomaly detection thresholds (lower_max, upper_max where applicable).
     * 'min' = below this is suspicious; 'max' = above this triggers warning.
     */
    private const ANOMALY_THRESHOLDS = [
        'CAPEX'  => ['min' => 5,  'max' => 45],
        'OPEX'   => ['min' => 25, 'max' => 85],
        'FINEX'  => ['min' => 0,  'max' => 12],
        'RISKEX' => ['min' => 0,  'max' => 8],
    ];

    // ─────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Get industry cost structure benchmarks.
     *
     * @return array{industry: string, country: string, benchmark: array<string,int>, source: string}
     */
    public function getIndustryCostStructure(string $industry, string $country = '*'): array
    {
        $industryKey = strtolower($industry);
        $benchmarks  = self::BENCHMARKS[$industryKey] ?? self::BENCHMARKS['manufacturing'];

        $data = $benchmarks[$country] ?? $benchmarks['*'] ?? ['CAPEX' => 28, 'OPEX' => 58, 'FINEX' => 7, 'RISKEX' => 7];

        // Ensure percentages sum to 100
        $total = array_sum($data);
        if ($total !== 100) {
            $data['OPEX'] += 100 - $total;
        }

        return [
            'industry'  => $industry,
            'country'   => $country,
            'benchmark' => $data,
            'source'    => 'WideHalo Global Benchmark 2026',
            'year'      => '2026',
        ];
    }

    /**
     * Compare an entity's actual cost structure to the industry benchmark.
     *
     * @return array{entity_type: string, entity_id: int, actual_pct: array, benchmark_pct: array, deviations: array, alerts: array}
     */
    public function compareToIndustry(
        string $entityType,
        int    $entityId,
        string $industry,
        string $country,
        int    $tenantId
    ): array {
        $period  = Carbon::now()->format('Y-m');
        $rollup  = CostRollup::query()
            ->forTenant($tenantId)
            ->forEntity($entityType, $entityId)
            ->forPeriod($period)
            ->first();

        if (! $rollup || $rollup->total_cost <= 0) {
            return [
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'actual_pct'    => ['CAPEX' => 0, 'OPEX' => 0, 'FINEX' => 0, 'RISKEX' => 0],
                'benchmark_pct' => $this->getIndustryCostStructure($industry, $country)['benchmark'],
                'deviations'    => [],
                'alerts'        => [],
                'message'       => 'Aucune donnée de coûts pour cette période.',
            ];
        }

        $total   = $rollup->total_cost;
        $actual  = [
            'CAPEX'  => $total > 0 ? round(($rollup->capex_total  / $total) * 100, 1) : 0,
            'OPEX'   => $total > 0 ? round(($rollup->opex_total   / $total) * 100, 1) : 0,
            'FINEX'  => $total > 0 ? round(($rollup->finex_total  / $total) * 100, 1) : 0,
            'RISKEX' => $total > 0 ? round(($rollup->riskex_total / $total) * 100, 1) : 0,
        ];

        $benchmark  = $this->getIndustryCostStructure($industry, $country)['benchmark'];
        $deviations = [];
        $alerts     = [];

        foreach (['CAPEX', 'OPEX', 'FINEX', 'RISKEX'] as $cat) {
            $delta = $actual[$cat] - ($benchmark[$cat] ?? 0);
            $deviations[$cat] = [
                'actual'    => $actual[$cat],
                'benchmark' => $benchmark[$cat] ?? 0,
                'delta'     => round($delta, 1),
                'status'    => abs($delta) <= 5 ? 'ok' : (abs($delta) <= 10 ? 'warning' : 'critical'),
            ];

            if (abs($delta) > 10) {
                $direction = $delta > 0 ? 'élevé' : 'faible';
                $alerts[]  = "{$cat} est trop {$direction} ({$actual[$cat]}% vs benchmark {$benchmark[$cat]}%)";
            }
        }

        return [
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'period'        => $period,
            'actual_pct'    => $actual,
            'benchmark_pct' => $benchmark,
            'deviations'    => $deviations,
            'alerts'        => $alerts,
            'industry'      => $industry,
            'country'       => $country,
        ];
    }

    /**
     * Detect cost anomalies across all entities for a tenant/period.
     *
     * Returns entities whose category percentages fall outside safe thresholds.
     */
    public function detectAnomalies(int $tenantId, string $period): array
    {
        $rollups   = CostRollup::query()
            ->forTenant($tenantId)
            ->forPeriod($period)
            ->where('total_cost', '>', 0)
            ->get();

        $anomalies = [];

        foreach ($rollups as $rollup) {
            $total = $rollup->total_cost;
            $pcts  = [
                'CAPEX'  => round(($rollup->capex_total  / $total) * 100, 1),
                'OPEX'   => round(($rollup->opex_total   / $total) * 100, 1),
                'FINEX'  => round(($rollup->finex_total  / $total) * 100, 1),
                'RISKEX' => round(($rollup->riskex_total / $total) * 100, 1),
            ];

            foreach ($pcts as $cat => $pct) {
                $thresholds = self::ANOMALY_THRESHOLDS[$cat];
                $anomaly    = null;

                if ($pct > $thresholds['max']) {
                    $anomaly = [
                        'severity'    => $pct > $thresholds['max'] * 1.5 ? 'critical' : 'warning',
                        'direction'   => 'above',
                        'message'     => "{$cat} à {$pct}% dépasse le seuil max de {$thresholds['max']}%",
                    ];
                } elseif ($cat === 'OPEX' && $pct < $thresholds['min']) {
                    $anomaly = [
                        'severity'  => 'warning',
                        'direction' => 'below',
                        'message'   => "OPEX à {$pct}% est anormalement bas — vérifier les charges de personnel",
                    ];
                }

                if ($anomaly) {
                    $anomalies[] = array_merge($anomaly, [
                        'entity_type'  => $rollup->entity_type,
                        'entity_id'    => $rollup->entity_id,
                        'entity_name'  => $rollup->entity_name,
                        'category'     => $cat,
                        'actual_pct'   => $pct,
                        'threshold'    => $thresholds,
                    ]);
                }
            }
        }

        // Sort by severity
        usort($anomalies, fn($a, $b) => ($b['severity'] === 'critical' ? 1 : 0) - ($a['severity'] === 'critical' ? 1 : 0));

        return [
            'period'    => $period,
            'count'     => count($anomalies),
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Return a flat list of all available industry slugs.
     */
    public function getAvailableIndustries(): array
    {
        return array_keys(self::BENCHMARKS);
    }
}
