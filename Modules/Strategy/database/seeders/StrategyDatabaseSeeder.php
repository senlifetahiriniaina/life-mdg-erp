<?php

declare(strict_types=1);

namespace Modules\Strategy\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Strategy module reference data:
 *  - 32 industry benchmarks (WideHalo Global 2026, WW / general)
 *  - 10 pre-seeded Pearson correlations (SME knowledge base)
 *
 * All inserts are idempotent via updateOrInsert.
 */
class StrategyDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBenchmarks();
        $this->seedCorrelations();

        $this->command?->info('✓ Strategy: benchmarks + correlations seedés.');
    }

    // ── Benchmarks ───────────────────────────────────────────────────────────

    private function seedBenchmarks(): void
    {
        $now  = now();
        $year = 2026;

        $benchmarks = [
            // Accounting
            ['ratio_name' => 'current_ratio',        'module' => 'Accounting',     'p25' => 1.1,       'median' => 1.5,       'p75' => 2.2],
            ['ratio_name' => 'debt_to_equity',        'module' => 'Accounting',     'p25' => 0.3,       'median' => 0.7,       'p75' => 1.4],
            ['ratio_name' => 'net_profit_margin',     'module' => 'Accounting',     'p25' => 5.0,       'median' => 10.0,      'p75' => 18.0],
            ['ratio_name' => 'ebitda_margin',         'module' => 'Accounting',     'p25' => 8.0,       'median' => 15.0,      'p75' => 25.0],
            ['ratio_name' => 'dso',                   'module' => 'Accounting',     'p25' => 28.0,      'median' => 40.0,      'p75' => 60.0],
            ['ratio_name' => 'dpo',                   'module' => 'Accounting',     'p25' => 25.0,      'median' => 38.0,      'p75' => 55.0],
            // CRM
            ['ratio_name' => 'lead_conversion_rate',  'module' => 'CRM',            'p25' => 10.0,      'median' => 18.0,      'p75' => 30.0],
            ['ratio_name' => 'cac',                   'module' => 'CRM',            'p25' => 50000.0,   'median' => 90000.0,   'p75' => 180000.0],
            ['ratio_name' => 'clv',                   'module' => 'CRM',            'p25' => 300000.0,  'median' => 800000.0,  'p75' => 2000000.0],
            ['ratio_name' => 'win_rate',              'module' => 'CRM',            'p25' => 20.0,      'median' => 30.0,      'p75' => 45.0],
            ['ratio_name' => 'pipeline_velocity',     'module' => 'CRM',            'p25' => 1000000.0, 'median' => 2500000.0, 'p75' => 6000000.0],
            // HR
            ['ratio_name' => 'turnover_rate',         'module' => 'HR',             'p25' => 8.0,       'median' => 14.0,      'p75' => 22.0],
            ['ratio_name' => 'absenteeism_rate',      'module' => 'HR',             'p25' => 2.0,       'median' => 4.0,       'p75' => 7.0],
            ['ratio_name' => 'revenue_per_employee',  'module' => 'HR',             'p25' => 3000000.0, 'median' => 6500000.0, 'p75' => 14000000.0],
            ['ratio_name' => 'training_roi',          'module' => 'HR',             'p25' => 50.0,      'median' => 120.0,     'p75' => 250.0],
            ['ratio_name' => 'time_to_fill',          'module' => 'HR',             'p25' => 20.0,      'median' => 32.0,      'p75' => 55.0],
            // Inventory
            ['ratio_name' => 'inventory_turnover',    'module' => 'Inventory',      'p25' => 3.0,       'median' => 5.5,       'p75' => 9.0],
            ['ratio_name' => 'stockout_rate',         'module' => 'Inventory',      'p25' => 1.0,       'median' => 3.0,       'p75' => 6.0],
            ['ratio_name' => 'fill_rate',             'module' => 'Inventory',      'p25' => 88.0,      'median' => 94.0,      'p75' => 98.0],
            ['ratio_name' => 'carrying_cost_ratio',   'module' => 'Inventory',      'p25' => 12.0,      'median' => 20.0,      'p75' => 30.0],
            // Sales
            ['ratio_name' => 'revenue_growth_rate',   'module' => 'Sales',          'p25' => 5.0,       'median' => 12.0,      'p75' => 25.0],
            ['ratio_name' => 'avg_deal_size',         'module' => 'Sales',          'p25' => 150000.0,  'median' => 350000.0,  'p75' => 800000.0],
            ['ratio_name' => 'sales_cycle_length',    'module' => 'Sales',          'p25' => 18.0,      'median' => 35.0,      'p75' => 65.0],
            ['ratio_name' => 'quota_attainment',      'module' => 'Sales',          'p25' => 65.0,      'median' => 82.0,      'p75' => 100.0],
            // Manufacturing
            ['ratio_name' => 'oee',                   'module' => 'Manufacturing',  'p25' => 55.0,      'median' => 72.0,      'p75' => 85.0],
            ['ratio_name' => 'defect_rate',           'module' => 'Manufacturing',  'p25' => 0.5,       'median' => 1.8,       'p75' => 4.0],
            ['ratio_name' => 'production_efficiency', 'module' => 'Manufacturing',  'p25' => 68.0,      'median' => 80.0,      'p75' => 92.0],
            ['ratio_name' => 'scrap_rate',            'module' => 'Manufacturing',  'p25' => 1.0,       'median' => 2.5,       'p75' => 5.0],
            // Helpdesk
            ['ratio_name' => 'first_response_time',   'module' => 'Helpdesk',       'p25' => 5.0,       'median' => 14.0,      'p75' => 30.0],
            ['ratio_name' => 'resolution_rate',       'module' => 'Helpdesk',       'p25' => 78.0,      'median' => 88.0,      'p75' => 96.0],
            ['ratio_name' => 'csat_score',            'module' => 'Helpdesk',       'p25' => 3.5,       'median' => 4.1,       'p75' => 4.7],
            ['ratio_name' => 'escalation_rate',       'module' => 'Helpdesk',       'p25' => 3.0,       'median' => 8.0,       'p75' => 15.0],
        ];

        foreach ($benchmarks as $b) {
            DB::table('strategy_industry_benchmarks')->updateOrInsert(
                ['ratio_name' => $b['ratio_name'], 'country' => 'WW', 'industry' => 'general', 'year' => $year],
                [
                    'module'     => $b['module'],
                    'p25'        => $b['p25'],
                    'median'     => $b['median'],
                    'p75'        => $b['p75'],
                    'source'     => 'WideHalo Global Benchmark 2026',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    // ── Correlations ─────────────────────────────────────────────────────────

    private function seedCorrelations(): void
    {
        $now = now();

        $correlations = [
            ['kpi_a' => 'CRM:win_rate',                   'kpi_b' => 'Sales:revenue_growth_rate',    'coefficient' => 0.82,  'lag' => 0, 'confidence' => 92.0],
            ['kpi_a' => 'CRM:lead_conversion_rate',       'kpi_b' => 'Accounting:net_profit_margin', 'coefficient' => 0.71,  'lag' => 1, 'confidence' => 85.0],
            ['kpi_a' => 'HR:turnover_rate',               'kpi_b' => 'Sales:quota_attainment',       'coefficient' => -0.68, 'lag' => 2, 'confidence' => 80.0],
            ['kpi_a' => 'Inventory:fill_rate',            'kpi_b' => 'Helpdesk:csat_score',          'coefficient' => 0.75,  'lag' => 0, 'confidence' => 88.0],
            ['kpi_a' => 'Manufacturing:oee',              'kpi_b' => 'Accounting:ebitda_margin',     'coefficient' => 0.79,  'lag' => 1, 'confidence' => 87.0],
            ['kpi_a' => 'HR:absenteeism_rate',            'kpi_b' => 'Manufacturing:defect_rate',    'coefficient' => 0.63,  'lag' => 0, 'confidence' => 74.0],
            ['kpi_a' => 'CRM:cac',                        'kpi_b' => 'Accounting:net_profit_margin', 'coefficient' => -0.61, 'lag' => 2, 'confidence' => 76.0],
            ['kpi_a' => 'Inventory:stockout_rate',        'kpi_b' => 'Sales:revenue_growth_rate',    'coefficient' => -0.58, 'lag' => 1, 'confidence' => 70.0],
            ['kpi_a' => 'Helpdesk:first_response_time',   'kpi_b' => 'Helpdesk:csat_score',          'coefficient' => -0.84, 'lag' => 0, 'confidence' => 95.0],
            ['kpi_a' => 'HR:training_roi',                'kpi_b' => 'Sales:quota_attainment',       'coefficient' => 0.56,  'lag' => 3, 'confidence' => 68.0],
        ];

        foreach ($correlations as $c) {
            DB::table('strategy_correlations')->updateOrInsert(
                ['kpi_a' => $c['kpi_a'], 'kpi_b' => $c['kpi_b'], 'lag_periods' => $c['lag']],
                [
                    'coefficient'      => $c['coefficient'],
                    'confidence'       => $c['confidence'],
                    'last_computed_at' => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]
            );
        }
    }
}
