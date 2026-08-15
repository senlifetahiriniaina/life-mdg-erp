<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('achats_suppliers')) {
            Schema::table('achats_suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('achats_suppliers', 'contact_person')) {
                    $table->string('contact_person')->nullable();
                }
                if (! Schema::hasColumn('achats_suppliers', 'address')) {
                    $table->string('address')->nullable();
                }
                if (! Schema::hasColumn('achats_suppliers', 'city')) {
                    $table->string('city')->nullable();
                }
                if (! Schema::hasColumn('achats_suppliers', 'tax_number')) {
                    $table->string('tax_number')->nullable();
                }
                if (! Schema::hasColumn('achats_suppliers', 'payment_terms')) {
                    $table->string('payment_terms')->nullable();
                }
                if (! Schema::hasColumn('achats_suppliers', 'lead_time_days')) {
                    $table->integer('lead_time_days')->nullable();
                }
                if (! Schema::hasColumn('achats_suppliers', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('achats_purchase_order_lines')) {
            Schema::table('achats_purchase_order_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('achats_purchase_order_lines', 'product_id')) {
                    $table->unsignedBigInteger('product_id')->nullable();
                }
                if (! Schema::hasColumn('achats_purchase_order_lines', 'unit')) {
                    $table->string('unit', 20)->nullable();
                }
                if (! Schema::hasColumn('achats_purchase_order_lines', 'tax_rate')) {
                    $table->decimal('tax_rate', 5, 2)->default(0);
                }
                if (! Schema::hasColumn('achats_purchase_order_lines', 'received_qty')) {
                    $table->decimal('received_qty', 12, 4)->default(0);
                }
                if (! Schema::hasColumn('achats_purchase_order_lines', 'invoiced_qty')) {
                    $table->decimal('invoiced_qty', 12, 4)->default(0);
                }
                if (! Schema::hasColumn('achats_purchase_order_lines', 'line_status')) {
                    $table->string('line_status', 20)->default('pending');
                }
                if (! Schema::hasColumn('achats_purchase_order_lines', 'line_total')) {
                    // Additive: the model/casts use `line_total`, the original migration named
                    // this column `total_price` — kept as-is, not renamed (additive-only policy).
                    $table->decimal('line_total', 15, 4)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('achats_suppliers')) {
            Schema::table('achats_suppliers', function (Blueprint $table) {
                $table->dropColumn(['contact_person', 'address', 'city', 'tax_number', 'payment_terms']);
            });
        }

        if (Schema::hasTable('achats_purchase_order_lines')) {
            Schema::table('achats_purchase_order_lines', function (Blueprint $table) {
                $table->dropColumn(['product_id', 'unit', 'tax_rate', 'received_qty', 'invoiced_qty', 'line_status', 'line_total']);
            });
        }
    }
};
