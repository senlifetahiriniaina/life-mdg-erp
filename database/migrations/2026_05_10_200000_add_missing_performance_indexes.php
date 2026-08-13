<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds high-impact performance indexes identified in the May 2026 audit.
 *
 * Several of the originally requested indexes already exist from the
 * 2026_05_09_000001_add_missing_performance_indexes migration. This migration
 * adds only the remaining new indexes, each guarded against duplicate creation.
 *
 * Notes on crm_activities:
 *   The table uses morphs('subject') — there is no direct contact_id column.
 *   The equivalent composite index is on (subject_type, subject_id, created_at),
 *   which optimises queries that filter activities for a specific model (e.g. a
 *   Contact) ordered by date.
 */
return new class extends Migration
{
    public function up(): void
    {
        // inventory_movements — (product_id, warehouse_id, created_at)
        // Already covered by inventory_movements_product_warehouse_idx from the
        // 2026_05_09 migration. Guarded to be safe.
        if (
            Schema::hasTable('inventory_movements') &&
            ! Schema::hasIndex('inventory_movements', 'idx_inv_movements_product_date')
        ) {
            Schema::table('inventory_movements', function (Blueprint $table): void {
                $table->index(
                    ['product_id', 'warehouse_id', 'created_at'],
                    'idx_inv_movements_product_date'
                );
            });
        }

        // hd_tickets — (sla_id, sla_due_at)
        // Already covered by hd_tickets_sla_due_idx from the 2026_05_09 migration.
        if (
            Schema::hasTable('hd_tickets') &&
            ! Schema::hasIndex('hd_tickets', 'idx_hd_tickets_sla')
        ) {
            Schema::table('hd_tickets', function (Blueprint $table): void {
                $table->index(['sla_id', 'sla_due_at'], 'idx_hd_tickets_sla');
            });
        }

        // mfg_production_orders — (status, scheduled_date)
        // The original create migration already has index(['status', 'scheduled_date']).
        // The 2026_05_09 migration added [bom_id, status]. This guard ensures we
        // do not create a duplicate of the original index under a new name.
        if (
            Schema::hasTable('mfg_production_orders') &&
            ! Schema::hasIndex('mfg_production_orders', 'idx_mfg_prod_orders_status_date')
        ) {
            Schema::table('mfg_production_orders', function (Blueprint $table): void {
                $table->index(
                    ['status', 'scheduled_date'],
                    'idx_mfg_prod_orders_status_date'
                );
            });
        }

        // crm_activities — morph-based contact lookup by (subject_type, subject_id, created_at)
        // The table has no direct contact_id column; morphs('subject') is the FK pattern.
        if (
            Schema::hasTable('crm_activities') &&
            ! Schema::hasIndex('crm_activities', 'idx_crm_activities_subject_created')
        ) {
            Schema::table('crm_activities', function (Blueprint $table): void {
                $table->index(
                    ['subject_type', 'subject_id', 'created_at'],
                    'idx_crm_activities_subject_created'
                );
            });
        }

        // acc_journal_entry_lines — (account_id, created_at)
        // Already covered by acc_journal_entry_lines_account_date_idx from the
        // 2026_05_09 migration. Guarded to be safe.
        if (
            Schema::hasTable('acc_journal_entry_lines') &&
            ! Schema::hasIndex('acc_journal_entry_lines', 'idx_acc_jel_account_created')
        ) {
            Schema::table('acc_journal_entry_lines', function (Blueprint $table): void {
                $table->index(
                    ['account_id', 'created_at'],
                    'idx_acc_jel_account_created'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('acc_journal_entry_lines') && Schema::hasIndex('acc_journal_entry_lines', 'idx_acc_jel_account_created')) {
            Schema::table('acc_journal_entry_lines', function (Blueprint $table): void {
                $table->dropIndex('idx_acc_jel_account_created');
            });
        }

        if (Schema::hasTable('crm_activities') && Schema::hasIndex('crm_activities', 'idx_crm_activities_subject_created')) {
            Schema::table('crm_activities', function (Blueprint $table): void {
                $table->dropIndex('idx_crm_activities_subject_created');
            });
        }

        if (Schema::hasTable('mfg_production_orders') && Schema::hasIndex('mfg_production_orders', 'idx_mfg_prod_orders_status_date')) {
            Schema::table('mfg_production_orders', function (Blueprint $table): void {
                $table->dropIndex('idx_mfg_prod_orders_status_date');
            });
        }

        if (Schema::hasTable('hd_tickets') && Schema::hasIndex('hd_tickets', 'idx_hd_tickets_sla')) {
            Schema::table('hd_tickets', function (Blueprint $table): void {
                $table->dropIndex('idx_hd_tickets_sla');
            });
        }

        if (Schema::hasTable('inventory_movements') && Schema::hasIndex('inventory_movements', 'idx_inv_movements_product_date')) {
            Schema::table('inventory_movements', function (Blueprint $table): void {
                $table->dropIndex('idx_inv_movements_product_date');
            });
        }
    }
};
