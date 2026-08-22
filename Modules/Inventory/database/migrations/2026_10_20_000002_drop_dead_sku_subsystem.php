<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.22 (Modules\Inventory 14-layer deep audit, layer 9 —
 * fake/dead): `Modules\Inventory\Models\SKU` + `Services\StockService`
 * modeled a completely separate, parallel "SKU x Warehouse" stock-tracking
 * concept, never integrated with the real, live, routed `Product`/`Stock`/
 * `StockMovement`/`InventoryService` subsystem the rest of this module
 * actually uses. Confirmed via a repo-wide grep that both had zero real
 * callers anywhere — only their own 3 isolated root-level test files
 * (`tests/Unit/Models/Inventory/SKUTest.php`,
 * `tests/Unit/Services/Inventory/StockServiceTest.php`,
 * `tests/Integration/Inventory/StockManagementTest.php`), which is why
 * this dead subtree survived every prior module-scoped Pest run — the
 * same root-level-test blind spot already documented for the CRM/HR deep
 * audits (Chantier 32.13-32.16). Deleted the model/service/factory/tests
 * alongside this migration.
 *
 * `inventory_stock_movements.sku_id` (added by the same original migration
 * that created `inventory_skus`) was never written by any real controller
 * either (confirmed via grep) — a dangling FK to the dead concept. Both
 * dropped here; `inventory_stock_movements.reference` (added by that same
 * original migration) is real and used by `StockMovement::$fillable` — left
 * untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_stock_movements') && Schema::hasColumn('inventory_stock_movements', 'sku_id')) {
            Schema::table('inventory_stock_movements', function (Blueprint $table) {
                // SQLite doesn't implicitly drop the column's index when the
                // column itself is dropped (unlike MySQL) — must be dropped
                // explicitly first or the migration fails with "no such
                // column: sku_id" while trying to rebuild the index.
                $table->dropIndex('inventory_stock_movements_sku_id_index');
                $table->dropColumn('sku_id');
            });
        }

        Schema::dropIfExists('inventory_skus');
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_skus')) {
            Schema::create('inventory_skus', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('unit')->nullable();
                $table->decimal('reorder_level', 15, 2)->default(0);
                $table->decimal('reorder_point', 15, 2)->default(0);
                $table->decimal('reorder_qty', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('inventory_stock_movements') && ! Schema::hasColumn('inventory_stock_movements', 'sku_id')) {
            Schema::table('inventory_stock_movements', function (Blueprint $table) {
                $table->unsignedBigInteger('sku_id')->nullable()->index();
            });
        }
    }
};
