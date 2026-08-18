<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 10: achats_purchase_receipt_lines was left as a bare catch-all
 * stub (id/receipt_id/order_line_id/quantity_received/timestamps) — it
 * doesn't match PurchaseReceiptLine::$fillable at all
 * (purchase_order_line_id/product_id/quality_status/variance_qty/notes),
 * and doesn't even have the right FK column name (order_line_id vs the
 * model's real purchase_order_line_id). PurchaseReceiptController was
 * fully stubbed until this chantier wired it to the real
 * PurchaseReceiptService — this patch closes the schema gap that would
 * otherwise make every real receipt-line write fatal.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('achats_purchase_receipt_lines')) {
            return;
        }

        Schema::table('achats_purchase_receipt_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('achats_purchase_receipt_lines', 'purchase_order_line_id')) {
                $table->unsignedBigInteger('purchase_order_line_id')->nullable();
            }
            if (! Schema::hasColumn('achats_purchase_receipt_lines', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable();
            }
            if (! Schema::hasColumn('achats_purchase_receipt_lines', 'quality_status')) {
                $table->string('quality_status', 32)->default('pending');
            }
            if (! Schema::hasColumn('achats_purchase_receipt_lines', 'variance_qty')) {
                $table->decimal('variance_qty', 12, 4)->default(0);
            }
            if (! Schema::hasColumn('achats_purchase_receipt_lines', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('achats_purchase_receipt_lines')) {
            return;
        }

        Schema::table('achats_purchase_receipt_lines', function (Blueprint $table) {
            $table->dropColumn(['purchase_order_line_id', 'product_id', 'quality_status', 'variance_qty', 'notes']);
        });
    }
};
