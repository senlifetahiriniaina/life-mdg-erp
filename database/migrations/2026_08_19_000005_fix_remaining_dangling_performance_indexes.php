<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same class of bug as 2026_08_17_000004_fix_payroll_employee_period_index_columns.php:
 * 2026_05_15_phase1_performance_indexes.php created two more indexes
 * referencing columns that never existed:
 * - idx_movements_product_date_type on inventory_movements
 *   ['product_id', 'movement_date', 'movement_type'] — the table's real
 *   columns (2026_05_01_000004_create_inventory_ecommerce_crm_tables.php)
 *   are 'type' (not movement_type) and created_at (no movement_date).
 * - idx_invoices_vendor_status_date on acc_invoices
 *   ['vendor_id', 'status', 'invoice_date'] — the table's real column
 *   (2026_05_29_000001_create_accounting_core_tables.php) is 'partner_id'
 *   (invoices don't distinguish a separate vendor_id; partner_type
 *   discriminates customer vs supplier).
 *
 * SQLite accepts CREATE INDEX over nonexistent columns without erroring, so
 * both went unnoticed until any unrelated ALTER TABLE ... RENAME COLUMN
 * forces SQLite to validate the whole schema, which then fails on these
 * dangling indexes (surfaced while fixing the Calendar attendees/reminders
 * calendar_event_id -> event_id rename).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_movements') && Schema::hasIndex('inventory_movements', 'idx_movements_product_date_type')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->dropIndex('idx_movements_product_date_type');
            });
        }

        if (Schema::hasTable('inventory_movements')
            && Schema::hasColumn('inventory_movements', 'type')
            && ! Schema::hasIndex('inventory_movements', 'idx_movements_product_type_created')
        ) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->index(['product_id', 'type', 'created_at'], 'idx_movements_product_type_created');
            });
        }

        if (Schema::hasTable('acc_invoices') && Schema::hasIndex('acc_invoices', 'idx_invoices_vendor_status_date')) {
            Schema::table('acc_invoices', function (Blueprint $table) {
                $table->dropIndex('idx_invoices_vendor_status_date');
            });
        }

        if (Schema::hasTable('acc_invoices')
            && Schema::hasColumn('acc_invoices', 'partner_id')
            && ! Schema::hasIndex('acc_invoices', 'idx_invoices_partner_status_date')
        ) {
            Schema::table('acc_invoices', function (Blueprint $table) {
                $table->index(['partner_id', 'status', 'invoice_date'], 'idx_invoices_partner_status_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_movements') && Schema::hasIndex('inventory_movements', 'idx_movements_product_type_created')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->dropIndex('idx_movements_product_type_created');
            });
        }

        if (Schema::hasTable('acc_invoices') && Schema::hasIndex('acc_invoices', 'idx_invoices_partner_status_date')) {
            Schema::table('acc_invoices', function (Blueprint $table) {
                $table->dropIndex('idx_invoices_partner_status_date');
            });
        }
    }
};
