<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('achats_rfqs')) {
            Schema::table('achats_rfqs', function (Blueprint $table) {
                if (! Schema::hasColumn('achats_rfqs', 'issued_date')) {
                    $table->date('issued_date')->nullable();
                }
                if (! Schema::hasColumn('achats_rfqs', 'deadline_date')) {
                    // Additive: the original stub migration named this column
                    // `deadline`, the model's real $fillable uses `deadline_date`.
                    $table->date('deadline_date')->nullable();
                }
            });
        }

        if (Schema::hasTable('achats_rfq_lines')) {
            Schema::table('achats_rfq_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('achats_rfq_lines', 'product_id')) {
                    $table->unsignedBigInteger('product_id')->nullable();
                }
                if (! Schema::hasColumn('achats_rfq_lines', 'unit')) {
                    $table->string('unit', 32)->nullable();
                }
                if (! Schema::hasColumn('achats_rfq_lines', 'required_date')) {
                    $table->date('required_date')->nullable();
                }
                if (! Schema::hasColumn('achats_rfq_lines', 'preferred_supplier_id')) {
                    $table->unsignedBigInteger('preferred_supplier_id')->nullable();
                }
                if (! Schema::hasColumn('achats_rfq_lines', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        if (Schema::hasTable('achats_supplier_quotes')) {
            Schema::table('achats_supplier_quotes', function (Blueprint $table) {
                if (! Schema::hasColumn('achats_supplier_quotes', 'quote_number')) {
                    $table->string('quote_number', 64)->nullable();
                }
                if (! Schema::hasColumn('achats_supplier_quotes', 'unit_price')) {
                    // Additive: the original stub migration named this column
                    // `total_amount`, the model's real $fillable has separate
                    // `unit_price`/`total_price`.
                    $table->decimal('unit_price', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('achats_supplier_quotes', 'total_price')) {
                    $table->decimal('total_price', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('achats_supplier_quotes', 'delivery_days')) {
                    $table->unsignedInteger('delivery_days')->nullable();
                }
                if (! Schema::hasColumn('achats_supplier_quotes', 'terms')) {
                    $table->text('terms')->nullable();
                }
                if (! Schema::hasColumn('achats_supplier_quotes', 'validity_date')) {
                    $table->date('validity_date')->nullable();
                }
                if (! Schema::hasColumn('achats_supplier_quotes', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (! Schema::hasColumn('achats_supplier_quotes', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('achats_purchase_receipts')) {
            Schema::table('achats_purchase_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('achats_purchase_receipts', 'receipt_number')) {
                    $table->string('receipt_number', 64)->nullable();
                }
                if (! Schema::hasColumn('achats_purchase_receipts', 'receipt_date')) {
                    // Additive: the original stub migration named this column
                    // `received_at`, the model's real $fillable uses `receipt_date`.
                    $table->date('receipt_date')->nullable();
                }
                if (! Schema::hasColumn('achats_purchase_receipts', 'received_by')) {
                    $table->unsignedBigInteger('received_by')->nullable();
                }
                if (! Schema::hasColumn('achats_purchase_receipts', 'warehouse_location')) {
                    $table->string('warehouse_location', 128)->nullable();
                }
                if (! Schema::hasColumn('achats_purchase_receipts', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('achats_purchase_receipts', 'total_received_value')) {
                    $table->decimal('total_received_value', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('achats_purchase_receipts', 'status')) {
                    $table->string('status', 32)->default('pending');
                }
                if (! Schema::hasColumn('achats_purchase_receipts', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('achats_rfqs')) {
            Schema::table('achats_rfqs', function (Blueprint $table) {
                $table->dropColumn(['issued_date', 'deadline_date']);
            });
        }

        if (Schema::hasTable('achats_rfq_lines')) {
            Schema::table('achats_rfq_lines', function (Blueprint $table) {
                $table->dropColumn(['product_id', 'unit', 'required_date', 'preferred_supplier_id', 'notes']);
            });
        }

        if (Schema::hasTable('achats_supplier_quotes')) {
            Schema::table('achats_supplier_quotes', function (Blueprint $table) {
                $table->dropColumn(['quote_number', 'unit_price', 'total_price', 'delivery_days', 'terms', 'validity_date', 'created_by', 'deleted_at']);
            });
        }

        if (Schema::hasTable('achats_purchase_receipts')) {
            Schema::table('achats_purchase_receipts', function (Blueprint $table) {
                $table->dropColumn(['receipt_number', 'receipt_date', 'received_by', 'warehouse_location', 'notes', 'total_received_value', 'status']);
                $table->dropSoftDeletes();
            });
        }
    }
};
