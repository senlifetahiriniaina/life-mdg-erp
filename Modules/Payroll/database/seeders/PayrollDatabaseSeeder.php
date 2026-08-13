<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Payroll module reference data:
 *  - Current payroll period (current month)
 *  - OHADA-compliant payroll component types (BRUT, cotisations, IPRES, CSS, IR)
 */
class PayrollDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCurrentPeriod();
        $this->seedPayrollComponents();

        $this->command?->info('✓ Payroll: période et composants seedés.');
    }

    private function seedCurrentPeriod(): void
    {
        if (DB::table('hr_payroll_periods')->exists()) {
            return;
        }

        $now   = now();
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();

        DB::table('hr_payroll_periods')->insert([
            'name'       => $start->translatedFormat('F Y'),
            'start_date' => $start->toDateString(),
            'end_date'   => $end->toDateString(),
            'status'     => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedPayrollComponents(): void
    {
        // Payroll component types table may not exist in all installs — guard
        if (!DB::getSchemaBuilder()->hasTable('hr_payroll_components')) {
            return;
        }

        if (DB::table('hr_payroll_components')->exists()) {
            return;
        }

        $now        = now();
        $components = [
            ['code' => 'BRUT',   'name' => 'Salaire de base',              'type' => 'earning',   'is_taxable' => true,  'rate' => null, 'is_mandatory' => true],
            ['code' => 'SURSALAIRE', 'name' => 'Sursalaire',              'type' => 'earning',   'is_taxable' => true,  'rate' => null, 'is_mandatory' => false],
            ['code' => 'IPRES_EE', 'name' => 'IPRES (employé)',           'type' => 'deduction', 'is_taxable' => false, 'rate' => 5.6,  'is_mandatory' => true],
            ['code' => 'IPRES_ER', 'name' => 'IPRES (employeur)',         'type' => 'employer',  'is_taxable' => false, 'rate' => 8.4,  'is_mandatory' => true],
            ['code' => 'CSS_EE',   'name' => 'CSS — Maladie (employé)',   'type' => 'deduction', 'is_taxable' => false, 'rate' => 3.0,  'is_mandatory' => true],
            ['code' => 'CSS_ER',   'name' => 'CSS — Maladie (employeur)', 'type' => 'employer',  'is_taxable' => false, 'rate' => 3.0,  'is_mandatory' => true],
            ['code' => 'IR',       'name' => 'Impôt sur le revenu (IRPP)','type' => 'deduction', 'is_taxable' => false, 'rate' => null, 'is_mandatory' => true],
            ['code' => 'TRANSPORT','name' => 'Indemnité de transport',    'type' => 'allowance', 'is_taxable' => false, 'rate' => null, 'is_mandatory' => false],
            ['code' => 'LOGEMENT', 'name' => 'Indemnité de logement',     'type' => 'allowance', 'is_taxable' => true,  'rate' => null, 'is_mandatory' => false],
        ];

        foreach ($components as $c) {
            DB::table('hr_payroll_components')->updateOrInsert(
                ['code' => $c['code']],
                array_merge($c, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
