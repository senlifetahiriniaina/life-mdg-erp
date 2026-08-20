<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19 (Lot 3 — Achats): a full re-audit found this module had
 * **zero** company/tenant scoping anywhere at all — not even the phantom
 * `users.tenant_id ?? default-bucket` pattern already fixed repeatedly
 * elsewhere this session, just a complete absence of any per-record
 * ownership check on any of the 5 core Achats entities. `achats_suppliers`/
 * `achats_purchase_orders`/`achats_rfqs` did carry an unused `tenant_id`
 * column from the original catch-all scaffold migration, but it was never
 * in any model's $fillable and never populated/read by any controller —
 * confirmed empirically with 2 real companies that a purchasing-manager
 * from Company A could list, view, edit, approve, and delete Company B's
 * suppliers/purchase-orders/RFQs/receipts/quotes.
 *
 * Adds a real, populated `company_id` (matching the `App\Models\Company`
 * boundary column used throughout the rest of the app, not the dead
 * `tenant_id` column) to the 5 tables that need it — `achats_purchase_receipts`
 * had neither column at all. Nullable/indexed, additive only — the existing
 * `tenant_id` columns are left in place (harmless dead columns, same
 * documented convention as the phantom `users.tenant_id`/`users.role`
 * columns elsewhere in this app).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['achats_suppliers', 'achats_purchase_orders', 'achats_rfqs', 'achats_purchase_receipts', 'achats_supplier_quotes'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('company_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['achats_suppliers', 'achats_purchase_orders', 'achats_rfqs', 'achats_purchase_receipts', 'achats_supplier_quotes'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('company_id');
                });
            }
        }
    }
};
