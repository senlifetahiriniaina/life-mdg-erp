<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.22 (Modules\Inventory, 14-layer deep audit) — this module was
 * already flagged (Chantier 19) as having "zero company/tenant scoping
 * module-wide", but that note was never acted on. Re-confirmed empirically
 * with 2 real companies + a real HTTP request against `GET
 * /api/v1/inventory/{products,warehouses,suppliers}`: any authenticated
 * user with an Inventory role could list/view/edit/delete any other
 * company's products, categories, warehouses, suppliers, stock movements,
 * and purchase orders. `Product` already carries a `tenant_id` column and
 * the `BelongsToTenant` trait (stancl/tenancy, keyed on the real
 * `tenancy()->tenant` context), but that mechanism is a no-op outside a
 * real tenant-domain request (confirmed: `tenant_id` was NULL on every
 * product created via the real API in the probe) — it does not close this
 * gap in practice, matching the same "phantom tenant_id" family of bugs
 * documented repeatedly elsewhere in this session.
 *
 * Full module-wide coverage (all ~19 resource types) was judged too large
 * for a single-module audit pass — scoped to the 6 highest-value resources
 * (master data + the two operationally/financially sensitive flows):
 * products, categories, warehouses, suppliers, stock movements, purchase
 * orders. `inventory_units`/`inventory_product_templates` are deliberately
 * left unscoped — both are shared reference/catalogue data seeded once for
 * every tenant by `DefaultDataSeeder`/`ProductTemplateSeeder`, not
 * per-company business data (matching the precedent already established
 * for `Modules\Shared`'s `shared_currencies`/`shared_countries`). The
 * remaining ~13 operational resource types (lots, picking, shipments, RMA,
 * cycle counts, demand forecasts, valuations, transfer orders, seasonal
 * factors, crossdock, wave picking, costing sheets, production orders)
 * are left as a documented open gap of the same severity as CRM/HR's own
 * equivalent leftover items from earlier chantiers this session.
 */
return new class extends Migration
{
    private array $tables = [
        'inventory_products',
        'inventory_categories',
        'inventory_warehouses',
        'inventory_suppliers',
        'inventory_stock_movements',
        'inventory_purchase_orders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('company_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('company_id');
                });
            }
        }
    }
};
