<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cross-module performance indexes. Each entry targets a table owned by a
     * module whose migration may run after this one (or may not exist yet),
     * so every index is applied defensively — identical to the guarded
     * pattern used in 2026_05_04_000002_add_performance_indexes.php.
     *
     * @var array<int, array{0:string,1:array<int,string>,2:string}>
     */
    private array $indexes = [
        ['hd_tickets',               ['sla_id', 'sla_due_at'],                'hd_tickets_sla_due_idx'],
        ['mfg_production_orders',     ['bom_id', 'status'],                    'mfg_production_orders_bom_status_idx'],
        ['inventory_movements',       ['product_id', 'warehouse_id', 'created_at'], 'inventory_movements_product_warehouse_idx'],
        ['acc_journal_entry_lines',   ['account_id', 'created_at'],            'acc_journal_entry_lines_account_date_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $indexName]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $missingColumns = array_filter(
                $columns,
                fn ($col) => !Schema::hasColumn($table, $col)
            );
            if ($missingColumns) {
                continue;
            }
            if (Schema::hasIndex($table, $indexName)) {
                continue;
            }
            Schema::table($table, function (Blueprint $bp) use ($columns, $indexName) {
                $bp->index($columns, $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $columns, $indexName]) {
            if (!Schema::hasTable($table) || !Schema::hasIndex($table, $indexName)) {
                continue;
            }
            Schema::table($table, function (Blueprint $bp) use ($indexName) {
                $bp->dropIndex($indexName);
            });
        }
    }
};
