<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return;
        }

        if (! Schema::hasColumn('sales_order_lines', 'discount_percent')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                // Additive: original migration named this column `discount_pct`, the
                // model $fillable/controller validation use `discount_percent` — added
                // as a new column rather than renaming (additive-only migration policy).
                $table->decimal('discount_percent', 5, 2)->default(0);
            });
        }

        if (Schema::hasColumn('sales_order_lines', 'product_name')) {
            // `product_name` is NOT NULL with no default, but has zero real consumers —
            // SalesOrderLine's $fillable never includes it and no service/controller in
            // this module writes it. Widen to nullable so real insert paths (which never
            // set it) stop failing on it, same pattern as A5's users.role fix.
            Schema::table('sales_order_lines', function (Blueprint $table) {
                $table->string('product_name')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return;
        }

        if (Schema::hasColumn('sales_order_lines', 'discount_percent')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }
    }
};
