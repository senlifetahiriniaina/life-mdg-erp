<?php

declare(strict_types=1);

namespace Modules\Strategy\Services;

/**
 * Calculates current values for all registered module ratios
 * and provides per-module ratio definitions with status (RAG).
 */
class StrategyRatioService
{
    public function __construct(
        private readonly KPIRegistryService   $registry,
        private readonly BenchmarkService     $benchmark,
    ) {}

    /**
     * Return all ratio definitions with current + benchmark values and RAG status.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function allRatiosWithStatus(string $tenantId = 'default'): array
    {
        $result = [];
        foreach ($this->ratioDefinitions() as $module => $ratios) {
            $result[$module] = array_map(
                fn(array $ratio) => $this->enrichRatio($ratio, $tenantId),
                $ratios
            );
        }
        return $result;
    }

    /**
     * Return ratios for a single module.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ratiosForModule(string $module, string $tenantId = 'default'): array
    {
        $definitions = $this->ratioDefinitions()[$module] ?? [];
        return array_map(
            fn(array $ratio) => $this->enrichRatio($ratio, $tenantId),
            $definitions
        );
    }

    /**
     * Static per-module ratio definitions.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function ratioDefinitions(): array
    {
        return [
            'Accounting' => [
                [
                    'key'               => 'current_ratio',
                    'name'              => 'Current Ratio',
                    'formula'           => 'current_assets / current_liabilities',
                    'unit'              => 'x',
                    'direction'         => 'up',
                    'target_min'        => 1.5,
                    'description'       => 'Mesure la capacité de l\'entreprise à couvrir ses dettes à court terme. Cible ≥ 1.5 pour une solvabilité saine.',
                    'benchmark_category' => 'liquidity',
                    'kpi_key'           => 'Accounting:current_ratio',
                ],
                [
                    'key'               => 'debt_to_equity',
                    'name'              => 'Debt-to-Equity',
                    'formula'           => 'total_debt / total_equity',
                    'unit'              => 'x',
                    'direction'         => 'down',
                    'target_max'        => 1.0,
                    'description'       => 'Ratio d\'endettement. Un ratio élevé indique un fort effet de levier financier.',
                    'benchmark_category' => 'leverage',
                    'kpi_key'           => 'Accounting:debt_to_equity',
                ],
                [
                    'key'               => 'net_profit_margin',
                    'name'              => 'Net Profit Margin',
                    'formula'           => 'net_profit / revenue × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 10.0,
                    'description'       => 'Pourcentage du chiffre d\'affaires qui devient bénéfice net. Indicateur central de rentabilité.',
                    'benchmark_category' => 'profitability',
                    'kpi_key'           => 'Accounting:net_profit_margin',
                ],
                [
                    'key'               => 'ebitda_margin',
                    'name'              => 'EBITDA Margin',
                    'formula'           => 'ebitda / revenue × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 15.0,
                    'description'       => 'Marge avant intérêts, impôts, dépréciations et amortissements. Proxy de la rentabilité opérationnelle.',
                    'benchmark_category' => 'profitability',
                    'kpi_key'           => 'Accounting:ebitda_margin',
                ],
                [
                    'key'               => 'dso',
                    'name'              => 'DSO (Days Sales Outstanding)',
                    'formula'           => 'accounts_receivable / (revenue / 365)',
                    'unit'              => 'jours',
                    'direction'         => 'down',
                    'target_max'        => 45.0,
                    'description'       => 'Délai moyen de paiement des clients. Un DSO élevé pèse sur la trésorerie.',
                    'benchmark_category' => 'efficiency',
                    'kpi_key'           => 'Accounting:dso',
                ],
                [
                    'key'               => 'dpo',
                    'name'              => 'DPO (Days Payable Outstanding)',
                    'formula'           => 'accounts_payable / (cogs / 365)',
                    'unit'              => 'jours',
                    'direction'         => 'up',
                    'target_min'        => 30.0,
                    'description'       => 'Délai moyen de paiement aux fournisseurs. Un DPO long améliore le BFR.',
                    'benchmark_category' => 'efficiency',
                    'kpi_key'           => 'Accounting:dpo',
                ],
            ],

            'CRM' => [
                [
                    'key'               => 'lead_conversion_rate',
                    'name'              => 'Lead Conversion Rate',
                    'formula'           => 'converted_leads / total_leads × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 20.0,
                    'description'       => 'Proportion de leads convertis en clients. Mesure l\'efficacité du tunnel de vente.',
                    'benchmark_category' => 'sales_efficiency',
                    'kpi_key'           => 'CRM:lead_conversion_rate',
                ],
                [
                    'key'               => 'cac',
                    'name'              => 'CAC (Customer Acquisition Cost)',
                    'formula'           => 'total_sales_marketing_spend / new_customers',
                    'unit'              => 'XOF',
                    'direction'         => 'down',
                    'target_max'        => 100000.0,
                    'description'       => 'Coût moyen d\'acquisition d\'un nouveau client. À comparer au CLV pour valider la rentabilité.',
                    'benchmark_category' => 'sales_efficiency',
                    'kpi_key'           => 'CRM:cac',
                ],
                [
                    'key'               => 'clv',
                    'name'              => 'CLV (Customer Lifetime Value)',
                    'formula'           => 'avg_purchase_value × purchase_frequency × customer_lifespan',
                    'unit'              => 'XOF',
                    'direction'         => 'up',
                    'target_min'        => 500000.0,
                    'description'       => 'Valeur totale générée par un client sur toute sa relation commerciale. Doit être >> CAC.',
                    'benchmark_category' => 'customer_value',
                    'kpi_key'           => 'CRM:clv',
                ],
                [
                    'key'               => 'win_rate',
                    'name'              => 'Win Rate',
                    'formula'           => 'won_opportunities / total_closed_opportunities × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 30.0,
                    'description'       => 'Taux de transformation des opportunités en ventes conclues.',
                    'benchmark_category' => 'sales_efficiency',
                    'kpi_key'           => 'CRM:win_rate',
                ],
                [
                    'key'               => 'pipeline_velocity',
                    'name'              => 'Pipeline Velocity',
                    'formula'           => '(opportunities × win_rate × avg_deal_size) / sales_cycle_days',
                    'unit'              => 'XOF/jour',
                    'direction'         => 'up',
                    'target_min'        => 2000000.0,
                    'description'       => 'Vitesse de génération de revenus dans le pipeline CRM.',
                    'benchmark_category' => 'sales_efficiency',
                    'kpi_key'           => 'CRM:pipeline_velocity',
                ],
            ],

            'HR' => [
                [
                    'key'               => 'turnover_rate',
                    'name'              => 'Turnover Rate',
                    'formula'           => 'departures / avg_headcount × 100',
                    'unit'              => '%',
                    'direction'         => 'down',
                    'target_max'        => 15.0,
                    'description'       => 'Taux de rotation du personnel. Un taux élevé génère des coûts de recrutement significatifs.',
                    'benchmark_category' => 'hr_efficiency',
                    'kpi_key'           => 'HR:turnover_rate',
                ],
                [
                    'key'               => 'absenteeism_rate',
                    'name'              => 'Absenteeism Rate',
                    'formula'           => 'absent_days / (working_days × headcount) × 100',
                    'unit'              => '%',
                    'direction'         => 'down',
                    'target_max'        => 5.0,
                    'description'       => 'Proportion de jours de travail perdus pour cause d\'absence.',
                    'benchmark_category' => 'hr_efficiency',
                    'kpi_key'           => 'HR:absenteeism_rate',
                ],
                [
                    'key'               => 'revenue_per_employee',
                    'name'              => 'Revenue per Employee',
                    'formula'           => 'revenue / headcount',
                    'unit'              => 'XOF',
                    'direction'         => 'up',
                    'target_min'        => 5000000.0,
                    'description'       => 'Productivité économique par employé. Indicateur de l\'efficience de la main-d\'œuvre.',
                    'benchmark_category' => 'productivity',
                    'kpi_key'           => 'HR:revenue_per_employee',
                ],
                [
                    'key'               => 'training_roi',
                    'name'              => 'Training ROI',
                    'formula'           => '(performance_gain_value - training_cost) / training_cost × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 100.0,
                    'description'       => 'Retour sur investissement de la formation. ROI > 100% = chaque franc investi en rapporte 2.',
                    'benchmark_category' => 'hr_efficiency',
                    'kpi_key'           => 'HR:training_roi',
                ],
                [
                    'key'               => 'time_to_fill',
                    'name'              => 'Time-to-Fill',
                    'formula'           => 'avg days from opening to hire',
                    'unit'              => 'jours',
                    'direction'         => 'down',
                    'target_max'        => 30.0,
                    'description'       => 'Délai moyen pour pourvoir un poste. Un délai long ralentit la croissance.',
                    'benchmark_category' => 'recruitment',
                    'kpi_key'           => 'HR:time_to_fill',
                ],
            ],

            'Inventory' => [
                [
                    'key'               => 'inventory_turnover',
                    'name'              => 'Inventory Turnover',
                    'formula'           => 'cogs / avg_inventory',
                    'unit'              => 'x',
                    'direction'         => 'up',
                    'target_min'        => 4.0,
                    'description'       => 'Fréquence de renouvellement du stock. Un turnover élevé réduit les coûts de stockage.',
                    'benchmark_category' => 'inventory_efficiency',
                    'kpi_key'           => 'Inventory:inventory_turnover',
                ],
                [
                    'key'               => 'stockout_rate',
                    'name'              => 'Stockout Rate',
                    'formula'           => 'stockout_events / total_sku_days × 100',
                    'unit'              => '%',
                    'direction'         => 'down',
                    'target_max'        => 3.0,
                    'description'       => 'Fréquence de rupture de stock. Impacte directement les ventes et la satisfaction client.',
                    'benchmark_category' => 'inventory_efficiency',
                    'kpi_key'           => 'Inventory:stockout_rate',
                ],
                [
                    'key'               => 'fill_rate',
                    'name'              => 'Fill Rate',
                    'formula'           => 'orders_fulfilled_complete / total_orders × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 95.0,
                    'description'       => 'Proportion de commandes livrées complètes du premier coup.',
                    'benchmark_category' => 'inventory_efficiency',
                    'kpi_key'           => 'Inventory:fill_rate',
                ],
                [
                    'key'               => 'carrying_cost_ratio',
                    'name'              => 'Carrying Cost Ratio',
                    'formula'           => 'carrying_cost / avg_inventory_value × 100',
                    'unit'              => '%',
                    'direction'         => 'down',
                    'target_max'        => 25.0,
                    'description'       => 'Coût de possession du stock en % de sa valeur. Inclut assurances, stockage, obsolescence.',
                    'benchmark_category' => 'inventory_efficiency',
                    'kpi_key'           => 'Inventory:carrying_cost_ratio',
                ],
            ],

            'Sales' => [
                [
                    'key'               => 'revenue_growth_rate',
                    'name'              => 'Revenue Growth Rate',
                    'formula'           => '(current_revenue - previous_revenue) / previous_revenue × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 10.0,
                    'description'       => 'Taux de croissance du chiffre d\'affaires. Indicateur de dynamisme commercial.',
                    'benchmark_category' => 'growth',
                    'kpi_key'           => 'Sales:revenue_growth_rate',
                ],
                [
                    'key'               => 'avg_deal_size',
                    'name'              => 'Average Deal Size',
                    'formula'           => 'total_revenue / number_of_deals',
                    'unit'              => 'XOF',
                    'direction'         => 'up',
                    'target_min'        => 300000.0,
                    'description'       => 'Valeur moyenne des transactions. Un deal size croissant indique une montée en gamme.',
                    'benchmark_category' => 'sales_performance',
                    'kpi_key'           => 'Sales:avg_deal_size',
                ],
                [
                    'key'               => 'sales_cycle_length',
                    'name'              => 'Sales Cycle Length',
                    'formula'           => 'avg days from first contact to close',
                    'unit'              => 'jours',
                    'direction'         => 'down',
                    'target_max'        => 45.0,
                    'description'       => 'Durée moyenne du cycle de vente. Un cycle court améliore la vélocité du pipeline.',
                    'benchmark_category' => 'sales_efficiency',
                    'kpi_key'           => 'Sales:sales_cycle_length',
                ],
                [
                    'key'               => 'quota_attainment',
                    'name'              => 'Quota Attainment',
                    'formula'           => 'actual_revenue / quota × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 80.0,
                    'description'       => 'Taux d\'atteinte du quota. Un score < 80% déclenche une révision de la stratégie commerciale.',
                    'benchmark_category' => 'sales_performance',
                    'kpi_key'           => 'Sales:quota_attainment',
                ],
            ],

            'Manufacturing' => [
                [
                    'key'               => 'oee',
                    'name'              => 'OEE (Overall Equipment Effectiveness)',
                    'formula'           => 'availability × performance × quality',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 85.0,
                    'description'       => 'Efficacité globale des équipements. OEE = disponibilité × performance × qualité. Classe mondiale = 85%.',
                    'benchmark_category' => 'production_efficiency',
                    'kpi_key'           => 'Manufacturing:oee',
                ],
                [
                    'key'               => 'defect_rate',
                    'name'              => 'Defect Rate',
                    'formula'           => 'defective_units / total_units × 100',
                    'unit'              => '%',
                    'direction'         => 'down',
                    'target_max'        => 2.0,
                    'description'       => 'Proportion d\'unités défectueuses. Indicateur qualité critique en production.',
                    'benchmark_category' => 'quality',
                    'kpi_key'           => 'Manufacturing:defect_rate',
                ],
                [
                    'key'               => 'production_efficiency',
                    'name'              => 'Production Efficiency',
                    'formula'           => 'standard_time / actual_time × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 80.0,
                    'description'       => 'Rapport entre temps standard et temps réel de production. Mesure la productivité opérationnelle.',
                    'benchmark_category' => 'production_efficiency',
                    'kpi_key'           => 'Manufacturing:production_efficiency',
                ],
                [
                    'key'               => 'scrap_rate',
                    'name'              => 'Scrap Rate',
                    'formula'           => 'scrap_cost / total_production_cost × 100',
                    'unit'              => '%',
                    'direction'         => 'down',
                    'target_max'        => 3.0,
                    'description'       => 'Proportion du coût de production perdue en rebuts. Impacte directement la marge.',
                    'benchmark_category' => 'quality',
                    'kpi_key'           => 'Manufacturing:scrap_rate',
                ],
            ],

            'Helpdesk' => [
                [
                    'key'               => 'first_response_time',
                    'name'              => 'First Response Time',
                    'formula'           => 'avg minutes from ticket creation to first agent response',
                    'unit'              => 'min',
                    'direction'         => 'down',
                    'target_max'        => 15.0,
                    'description'       => 'Délai moyen de première réponse aux tickets. SLA standard < 15 min pour tickets critiques.',
                    'benchmark_category' => 'customer_service',
                    'kpi_key'           => 'Helpdesk:first_response_time',
                ],
                [
                    'key'               => 'resolution_rate',
                    'name'              => 'Resolution Rate',
                    'formula'           => 'resolved_tickets / total_tickets × 100',
                    'unit'              => '%',
                    'direction'         => 'up',
                    'target_min'        => 90.0,
                    'description'       => 'Proportion de tickets résolus. Un faible taux indique des problèmes récurrents non traités.',
                    'benchmark_category' => 'customer_service',
                    'kpi_key'           => 'Helpdesk:resolution_rate',
                ],
                [
                    'key'               => 'csat_score',
                    'name'              => 'CSAT Score',
                    'formula'           => 'avg customer satisfaction rating',
                    'unit'              => '/5',
                    'direction'         => 'up',
                    'target_min'        => 4.0,
                    'description'       => 'Score de satisfaction client moyen. Indicateur de qualité du support perçue par les clients.',
                    'benchmark_category' => 'customer_service',
                    'kpi_key'           => 'Helpdesk:csat_score',
                ],
                [
                    'key'               => 'escalation_rate',
                    'name'              => 'Escalation Rate',
                    'formula'           => 'escalated_tickets / total_tickets × 100',
                    'unit'              => '%',
                    'direction'         => 'down',
                    'target_max'        => 10.0,
                    'description'       => 'Proportion de tickets remontés à un niveau supérieur. Indicateur de compétence L1.',
                    'benchmark_category' => 'customer_service',
                    'kpi_key'           => 'Helpdesk:escalation_rate',
                ],
            ],
        ];
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function enrichRatio(array $ratio, string $tenantId): array
    {
        [$module, $key] = explode(':', $ratio['kpi_key'] . ':');
        $currentValue   = $this->registry->getValue($module, $key);
        $benchmarkData  = $this->benchmark->getForRatio($ratio['key'], $tenantId);
        $status         = $this->computeStatus($ratio, $currentValue, $benchmarkData['median'] ?? null);
        $trend          = $this->generateMockTrend($currentValue, $ratio['direction']);

        return array_merge($ratio, [
            'current_value'   => $currentValue,
            'benchmark_value' => $benchmarkData['median'] ?? null,
            'benchmark_p25'   => $benchmarkData['p25'] ?? null,
            'benchmark_p75'   => $benchmarkData['p75'] ?? null,
            'percentile'      => $this->computePercentile($currentValue, $benchmarkData),
            'status'          => $status,   // 'green' | 'amber' | 'red'
            'trend'           => $trend,    // last 6 months mock
        ]);
    }

    private function computeStatus(array $ratio, float $value, ?float $benchmark): string
    {
        $direction = $ratio['direction'];

        if ($direction === 'up') {
            $target = $ratio['target_min'] ?? ($benchmark ?? null);
            if ($target === null) return 'amber';
            if ($value >= $target * 0.95) return 'green';
            if ($value >= $target * 0.75) return 'amber';
            return 'red';
        }

        if ($direction === 'down') {
            $target = $ratio['target_max'] ?? ($benchmark ?? null);
            if ($target === null) return 'amber';
            if ($value <= $target * 1.05) return 'green';
            if ($value <= $target * 1.25) return 'amber';
            return 'red';
        }

        return 'amber';
    }

    private function computePercentile(float $value, array $benchmarkData): ?int
    {
        if (empty($benchmarkData['p25']) || empty($benchmarkData['p75'])) {
            return null;
        }
        $p25 = (float) $benchmarkData['p25'];
        $p75 = (float) $benchmarkData['p75'];
        if ($value <= $p25) return 25;
        if ($value >= $p75) return 75;
        // Linear interpolation between P25 and P75
        $pct = ($value - $p25) / ($p75 - $p25) * 50 + 25;
        return (int) round($pct);
    }

    private function generateMockTrend(float $currentValue, string $direction): array
    {
        $trend    = [];
        $variance = $currentValue * 0.08;
        for ($i = 5; $i >= 0; $i--) {
            $offset  = $direction === 'up' ? -$variance * $i / 5 : $variance * $i / 5;
            $trend[] = round($currentValue + $offset + (mt_rand(-100, 100) / 100 * $variance * 0.3), 2);
        }
        $trend[] = $currentValue;
        return $trend;
    }
}
