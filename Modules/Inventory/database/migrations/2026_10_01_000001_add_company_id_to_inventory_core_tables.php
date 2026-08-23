<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32 — Inventory "core" (catalog/warehouse/stock + the newer
 * costing/production/sourcing subsystems) had zero company-based tenant
 * isolation anywhere — the same gap already found and fixed for CRM
 * (Chantier 10/19), Achats (Chantier 19 Lot 3), and Projects (Chantier 19
 * Lot 2). Confirmed via grep that none of `Product`/`Category`/`Warehouse`/
 * `Stock`/`StockMovement`/`Lot`/`Unit`/`ReorderRule`/`CostingSheet`/
 * `ProductionOrder`/`SourcingBenchmark` carried a real, populated
 * `company_id` column before this — `Product.tenant_id` is the well-
 * documented phantom column (real, migrated, never in `User::$fillable`,
 * never populated by any real registration path), and every other model
 * had no tenant/company concept at all.
 *
 * Additive, nullable, indexed `company_id` — matching the exact shape of
 * Achats' `2026_09_06_000001_add_company_id_to_achats_tables.php` and
 * CRM's equivalent precedent migrations. No backfill: none of these
 * tables ever had a real tenant column before, so there is nothing
 * meaningful to backfill from (a record with `company_id = NULL` is
 * treated as the same "untagged" bucket as a caller with no real
 * `company_id` yet, matching this app's established null==null
 * convention — never auto-denied).
 *
 * Deliberately excludes:
 * - `inventory_costing_sheet_lines` — a child/line-item table, resolves
 *   its tenant boundary via its parent CostingSheet relation instead
 *   (ScopesToCompany::assertSameCompanyViaParent(), mirroring
 *   Modules\Projects\...\ScopesToProjectCompany's shape for child models).
 * - `inventory_product_templates` — a deliberately global/company-less
 *   shared catalogue (confirmed via ProductTemplateSeeder having zero
 *   company_id references), matching acc_operation_templates elsewhere
 *   in this app. Not touched here.
 */
return new class extends Migration
{
    private const TABLES = [
        'inventory_products',
        'inventory_categories',
        'inventory_warehouses',
        'inventory_stock',
        'inventory_stock_movements',
        'inventory_lots',
        'inventory_units',
        'inventory_reorder_rules',
        'inventory_costing_sheets',
        'inventory_production_orders',
        'inventory_sourcing_benchmarks',
        // Api\SupplierController is real and distinct from Achats' Supplier
        // (own table, own purchaseOrders() relation onto Inventory's own
        // PurchaseOrder model) — not in the task's original table list, but
        // needed to actually scope SupplierController per the investigation
        // this migration's own docblock records below.
        'inventory_suppliers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('company_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('company_id');
                });
            }
        }
    }
};
