<?php

namespace Modules\Strategy\Services;

use Modules\Strategy\Models\StrategyKpi;
use Modules\Strategy\Models\StrategyKpiValue;

class KpiDataService
{
    /**
     * Registry of available metrics per ERP module.
     */
    const SOURCES = [
        'Accounting' => [
            'monthly_revenue'  => ['label' => 'CA mensuel', 'unit' => 'XOF', 'agg' => 'sum'],
            'monthly_expenses' => ['label' => 'Charges mensuelles', 'unit' => 'XOF', 'agg' => 'sum'],
            'cash_position'    => ['label' => 'Trésorerie', 'unit' => 'XOF', 'agg' => 'last'],
            'overdue_invoices' => ['label' => 'Factures en retard', 'unit' => '#', 'agg' => 'count'],
            'gross_margin_pct' => ['label' => 'Marge brute %', 'unit' => '%', 'agg' => 'avg'],
        ],
        'CRM' => [
            'leads_count'         => ['label' => 'Leads actifs', 'unit' => '#', 'agg' => 'count'],
            'opportunities_count' => ['label' => 'Opportunités', 'unit' => '#', 'agg' => 'count'],
            'pipeline_value'      => ['label' => 'Pipeline CRM', 'unit' => 'XOF', 'agg' => 'sum'],
            'conversion_rate'     => ['label' => 'Taux conversion', 'unit' => '%', 'agg' => 'avg'],
        ],
        'HR' => [
            'headcount'        => ['label' => 'Effectif', 'unit' => '#', 'agg' => 'count'],
            'open_positions'   => ['label' => 'Postes ouverts', 'unit' => '#', 'agg' => 'count'],
            'absenteeism_rate' => ['label' => 'Taux absentéisme', 'unit' => '%', 'agg' => 'avg'],
        ],
        'Inventory' => [
            'stock_value'    => ['label' => 'Valeur stock', 'unit' => 'XOF', 'agg' => 'sum'],
            'stockout_count' => ['label' => 'Ruptures de stock', 'unit' => '#', 'agg' => 'count'],
        ],
        'Sales' => [
            'orders_count' => ['label' => 'Commandes', 'unit' => '#', 'agg' => 'count'],
            'orders_value' => ['label' => 'CA commandes', 'unit' => 'XOF', 'agg' => 'sum'],
        ],
        'Helpdesk' => [
            'open_tickets'       => ['label' => 'Tickets ouverts', 'unit' => '#', 'agg' => 'count'],
            'avg_resolution_hrs' => ['label' => 'Délai résolution moy.', 'unit' => 'h', 'agg' => 'avg'],
        ],
    ];

    /**
     * Fetch live value from the relevant ERP module source.
     * Returns mock/default values since actual module tables may not be seeded.
     */
    public function fetchLiveValue(StrategyKpi $kpi): float
    {
        $module = $kpi->source_module;
        $key    = $kpi->source_key;

        // Mock values by source key pattern
        $mockValues = [
            'monthly_revenue'    => 50000000.0,
            'monthly_expenses'   => 35000000.0,
            'cash_position'      => 120000000.0,
            'overdue_invoices'   => 12.0,
            'gross_margin_pct'   => 30.0,
            'leads_count'        => 85.0,
            'opportunities_count' => 34.0,
            'pipeline_value'     => 180000000.0,
            'conversion_rate'    => 22.5,
            'headcount'          => 47.0,
            'open_positions'     => 6.0,
            'absenteeism_rate'   => 3.8,
            'stock_value'        => 75000000.0,
            'stockout_count'     => 4.0,
            'orders_count'       => 128.0,
            'orders_value'       => 95000000.0,
            'open_tickets'       => 23.0,
            'avg_resolution_hrs' => 8.5,
        ];

        return $mockValues[$key] ?? (float) rand(70, 95);
    }

    public function recordValue(StrategyKpi $kpi, float $value): StrategyKpiValue
    {
        return StrategyKpiValue::create([
            'kpi_id'      => $kpi->id,
            'value'       => $value,
            'recorded_at' => now(),
            'period'      => now()->format('Y-m'),
        ]);
    }

    public function getHistory(int $kpiId, int $days = 30): array
    {
        return StrategyKpiValue::where('kpi_id', $kpiId)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('recorded_at', 'asc')
            ->get()
            ->toArray();
    }

    public function getAvailableSources(): array
    {
        return self::SOURCES;
    }
}
