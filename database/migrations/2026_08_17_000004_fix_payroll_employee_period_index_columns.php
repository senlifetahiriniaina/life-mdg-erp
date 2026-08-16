<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two performance-index migrations created indexes on hr_payroll_records
 * referencing columns that never existed on that table:
 * - 2026_05_15_add_performance_indexes.php: idx_payroll_employee_period on
 *   ['employee_id', 'period_start', 'period_end'] -- the table's real
 *   columns (2026_05_01_000006_create_crm_pos_documents_tables.php) are
 *   pay_period_start/pay_period_end.
 * - 2026_05_15_phase1_performance_indexes.php: idx_payroll_employee_period_status
 *   on ['employee_id', 'period_id', 'status'] -- there is no period_id
 *   column, and no equivalent exists (this legacy table, per CLAUDE.md,
 *   was superseded by the dedicated Payroll module's own tables), so this
 *   one is dropped without a replacement.
 *
 * SQLite accepts CREATE INDEX over nonexistent columns without erroring,
 * so both went unnoticed until any unrelated ALTER TABLE ... RENAME COLUMN
 * forces SQLite to validate the whole schema, which then fails on these
 * dangling indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_payroll_records') && Schema::hasIndex('hr_payroll_records', 'idx_payroll_employee_period')) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                $table->dropIndex('idx_payroll_employee_period');
            });
        }

        if (Schema::hasTable('hr_payroll_records')
            && Schema::hasColumn('hr_payroll_records', 'pay_period_start')
            && Schema::hasColumn('hr_payroll_records', 'pay_period_end')
            && ! Schema::hasIndex('hr_payroll_records', 'idx_payroll_employee_period')
        ) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                $table->index(['employee_id', 'pay_period_start', 'pay_period_end'], 'idx_payroll_employee_period');
            });
        }

        if (Schema::hasTable('hr_payroll_records') && Schema::hasIndex('hr_payroll_records', 'idx_payroll_employee_period_status')) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                $table->dropIndex('idx_payroll_employee_period_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_payroll_records') && Schema::hasIndex('hr_payroll_records', 'idx_payroll_employee_period')) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                $table->dropIndex('idx_payroll_employee_period');
            });
        }
    }
};
