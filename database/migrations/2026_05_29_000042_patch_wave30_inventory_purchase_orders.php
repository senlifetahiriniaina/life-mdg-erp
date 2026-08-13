<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) return;
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // ── inventory_purchase_orders ─────────────────────────────────────
        $this->patch('inventory_purchase_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'supplier_id'))        $t->unsignedBigInteger('supplier_id')->nullable();
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'currency'))           $t->string('currency', 5)->default('USD');
            if (!Schema::hasColumn($table, 'shipping_cost'))      $t->decimal('shipping_cost', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'subtotal'))           $t->decimal('subtotal', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'tax_total'))          $t->decimal('tax_total', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'grand_total'))        $t->decimal('grand_total', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'approved_by'))        $t->unsignedBigInteger('approved_by')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))        $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'expected_date'))      $t->date('expected_date')->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'po_number'))          $t->string('po_number', 50)->nullable();
        });

        // ── inventory_purchase_order_items ────────────────────────────────
        $this->patch('inventory_purchase_order_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'purchase_order_id'))  $t->unsignedBigInteger('purchase_order_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))           $t->decimal('quantity', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'received_qty'))       $t->decimal('received_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'unit_price'))         $t->decimal('unit_price', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'tax_rate'))           $t->decimal('tax_rate', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'subtotal'))           $t->decimal('subtotal', 15, 2)->default(0);
        });

        // ── inventory_po_receipts ─────────────────────────────────────────
        $this->patch('inventory_po_receipts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'purchase_order_id'))  $t->unsignedBigInteger('purchase_order_id')->nullable();
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'received_by'))        $t->unsignedBigInteger('received_by')->nullable();
            if (!Schema::hasColumn($table, 'received_at'))        $t->timestamp('received_at')->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
        });

        // ── inventory_po_receipt_lines ────────────────────────────────────
        $this->patch('inventory_po_receipt_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'receipt_id'))         $t->unsignedBigInteger('receipt_id')->nullable();
            if (!Schema::hasColumn($table, 'po_item_id'))         $t->unsignedBigInteger('po_item_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'received_qty'))       $t->decimal('received_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
            if (!Schema::hasColumn($table, 'expiry_date'))        $t->date('expiry_date')->nullable();
        });
    }

    public function down(): void {}
};
