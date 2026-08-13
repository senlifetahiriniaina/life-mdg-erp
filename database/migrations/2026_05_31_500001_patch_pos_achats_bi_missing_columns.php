<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patch migration: fix missing columns for POS, Achats, and BI modules.
 */
return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // ── pos_orders ───────────────────────────────────────────────────
        $this->patch('pos_orders', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'session_id'))
                $t->unsignedBigInteger('session_id')->nullable();
            if (! Schema::hasColumn($table, 'cashier_id'))
                $t->unsignedBigInteger('cashier_id')->nullable();
            if (! Schema::hasColumn($table, 'customer_id'))
                $t->unsignedBigInteger('customer_id')->nullable();
            if (! Schema::hasColumn($table, 'reference'))
                $t->string('reference')->nullable();
            if (! Schema::hasColumn($table, 'subtotal'))
                $t->decimal('subtotal', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'tax_amount'))
                $t->decimal('tax_amount', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'discount_amount'))
                $t->decimal('discount_amount', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'total'))
                $t->decimal('total', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'currency'))
                $t->string('currency', 3)->default('XOF');
            if (! Schema::hasColumn($table, 'is_synced'))
                $t->boolean('is_synced')->default(false);
            if (! Schema::hasColumn($table, 'paid_at'))
                $t->timestamp('paid_at')->nullable();
            if (! Schema::hasColumn($table, 'table_number'))
                $t->string('table_number')->nullable();
            if (! Schema::hasColumn($table, 'payment_details'))
                $t->json('payment_details')->nullable();
            if (! Schema::hasColumn($table, 'split_payments'))
                $t->json('split_payments')->nullable();
        });

        // ── pos_loyalty_programs ─────────────────────────────────────────
        $this->patch('pos_loyalty_programs', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'name'))
                $t->string('name')->nullable();
            if (! Schema::hasColumn($table, 'points_per_currency'))
                $t->decimal('points_per_currency', 8, 4)->default(1);
            if (! Schema::hasColumn($table, 'currency_per_point'))
                $t->decimal('currency_per_point', 8, 4)->default(0.01);
            if (! Schema::hasColumn($table, 'min_points_redeem'))
                $t->integer('min_points_redeem')->default(100);
            if (! Schema::hasColumn($table, 'tiers_config'))
                $t->json('tiers_config')->nullable();
        });

        // ── pos_loyalty_transactions ──────────────────────────────────────
        $this->patch('pos_loyalty_transactions', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'loyalty_account_id'))
                $t->unsignedBigInteger('loyalty_account_id')->nullable();
            if (! Schema::hasColumn($table, 'order_id'))
                $t->unsignedBigInteger('order_id')->nullable();
            if (! Schema::hasColumn($table, 'type'))
                $t->string('type')->nullable();
            if (! Schema::hasColumn($table, 'points'))
                $t->integer('points')->default(0);
            if (! Schema::hasColumn($table, 'balance_after'))
                $t->integer('balance_after')->default(0);
            if (! Schema::hasColumn($table, 'note'))
                $t->string('note')->nullable();
            if (! Schema::hasColumn($table, 'expires_at'))
                $t->timestamp('expires_at')->nullable();
        });

        // ── pos_order_items ───────────────────────────────────────────────
        $this->patch('pos_order_items', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'order_id'))
                $t->unsignedBigInteger('order_id')->nullable();
            if (! Schema::hasColumn($table, 'product_id'))
                $t->unsignedBigInteger('product_id')->nullable();
            if (! Schema::hasColumn($table, 'product_name'))
                $t->string('product_name')->nullable();
            if (! Schema::hasColumn($table, 'sku'))
                $t->string('sku')->nullable();
            if (! Schema::hasColumn($table, 'quantity'))
                $t->decimal('quantity', 10, 3)->default(1);
            if (! Schema::hasColumn($table, 'unit_price'))
                $t->decimal('unit_price', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'discount_percent'))
                $t->decimal('discount_percent', 5, 2)->default(0);
            if (! Schema::hasColumn($table, 'tax_percent'))
                $t->decimal('tax_percent', 5, 2)->default(0);
            if (! Schema::hasColumn($table, 'total'))
                $t->decimal('total', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'notes'))
                $t->text('notes')->nullable();
        });

        // ── pos_order_payments ────────────────────────────────────────────
        $this->patch('pos_order_payments', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'order_id'))
                $t->unsignedBigInteger('order_id')->nullable();
            if (! Schema::hasColumn($table, 'payment_method'))
                $t->string('payment_method')->nullable();
            if (! Schema::hasColumn($table, 'amount'))
                $t->decimal('amount', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'reference'))
                $t->string('reference')->nullable();
            if (! Schema::hasColumn($table, 'processed_at'))
                $t->timestamp('processed_at')->nullable();
            if (! Schema::hasColumn($table, 'currency'))
                $t->string('currency', 3)->default('XOF');
            if (! Schema::hasColumn($table, 'change_given'))
                $t->decimal('change_given', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'transaction_id'))
                $t->string('transaction_id')->nullable();
            if (! Schema::hasColumn($table, 'is_partial'))
                $t->boolean('is_partial')->default(false);
        });

        // ── pos_returns ───────────────────────────────────────────────────
        $this->patch('pos_returns', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'original_order_id'))
                $t->unsignedBigInteger('original_order_id')->nullable();
            if (! Schema::hasColumn($table, 'cashier_id'))
                $t->unsignedBigInteger('cashier_id')->nullable();
            if (! Schema::hasColumn($table, 'reference'))
                $t->string('reference')->nullable();
            if (! Schema::hasColumn($table, 'reason'))
                $t->string('reason')->nullable();
            if (! Schema::hasColumn($table, 'refund_amount'))
                $t->decimal('refund_amount', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'total_refund'))
                $t->decimal('total_refund', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'refund_method'))
                $t->string('refund_method')->nullable();
            if (! Schema::hasColumn($table, 'notes'))
                $t->text('notes')->nullable();
            if (! Schema::hasColumn($table, 'items'))
                $t->json('items')->nullable();
            if (! Schema::hasColumn($table, 'processed_at'))
                $t->timestamp('processed_at')->nullable();
        });

        // ── pos_return_lines ──────────────────────────────────────────────
        $this->patch('pos_return_lines', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'return_id'))
                $t->unsignedBigInteger('return_id')->nullable();
            if (! Schema::hasColumn($table, 'order_line_id'))
                $t->unsignedBigInteger('order_line_id')->nullable();
            if (! Schema::hasColumn($table, 'original_item_id'))
                $t->unsignedBigInteger('original_item_id')->nullable();
            if (! Schema::hasColumn($table, 'product_id'))
                $t->unsignedBigInteger('product_id')->nullable();
            if (! Schema::hasColumn($table, 'quantity'))
                $t->decimal('quantity', 10, 3)->default(1);
            if (! Schema::hasColumn($table, 'unit_price'))
                $t->decimal('unit_price', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'refund_amount'))
                $t->decimal('refund_amount', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'reason'))
                $t->string('reason')->nullable();
            if (! Schema::hasColumn($table, 'restock'))
                $t->boolean('restock')->default(true);
        });

        // ── achats_purchase_orders ───────────────────────────────────────
        $this->patch('achats_purchase_orders', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'po_number'))
                $t->string('po_number')->nullable();
            if (! Schema::hasColumn($table, 'order_date'))
                $t->date('order_date')->nullable();
            if (! Schema::hasColumn($table, 'delivery_date'))
                $t->date('delivery_date')->nullable();
            if (! Schema::hasColumn($table, 'created_by'))
                $t->unsignedBigInteger('created_by')->nullable();
            if (! Schema::hasColumn($table, 'requested_by'))
                $t->unsignedBigInteger('requested_by')->nullable();
            if (! Schema::hasColumn($table, 'approved_by'))
                $t->unsignedBigInteger('approved_by')->nullable();
            if (! Schema::hasColumn($table, 'approved_at'))
                $t->timestamp('approved_at')->nullable();
            if (! Schema::hasColumn($table, 'subtotal'))
                $t->decimal('subtotal', 15, 4)->default(0);
            if (! Schema::hasColumn($table, 'tax_amount'))
                $t->decimal('tax_amount', 15, 4)->default(0);
            if (! Schema::hasColumn($table, 'shipping_cost'))
                $t->decimal('shipping_cost', 15, 4)->default(0);
            if (! Schema::hasColumn($table, 'total'))
                $t->decimal('total', 15, 4)->default(0);
            if (! Schema::hasColumn($table, 'notes'))
                $t->text('notes')->nullable();
        });

        // ── achats_suppliers ─────────────────────────────────────────────
        $this->patch('achats_suppliers', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'created_by'))
                $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── bi_kpis: recreate with code nullable ─────────────────────────
        if (Schema::hasTable('bi_kpis')) {
            $rows = \DB::table('bi_kpis')->get()->toArray();
            Schema::drop('bi_kpis');
            Schema::create('bi_kpis', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->text('description')->nullable();
                $table->string('category')->nullable();
                $table->string('formula')->nullable();
                $table->string('metric')->nullable();
                $table->string('source_module')->nullable();
                $table->decimal('current_value', 15, 4)->nullable();
                $table->decimal('target_value', 15, 4)->nullable();
                $table->decimal('threshold_warning', 15, 4)->nullable();
                $table->decimal('threshold_critical', 15, 4)->nullable();
                $table->decimal('value', 15, 4)->nullable();
                $table->decimal('target', 15, 4)->nullable();
                $table->string('unit')->nullable();
                $table->string('period')->nullable();
                $table->string('module')->nullable();
                $table->string('trend')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_calculated_at')->nullable();
                $table->timestamps();
                $table->index('category');
            });
            foreach ($rows as $row) {
                \DB::table('bi_kpis')->insert((array) $row);
            }
        }
    }

    public function down(): void
    {
        // Additive patch — no rollback needed
    }
};
