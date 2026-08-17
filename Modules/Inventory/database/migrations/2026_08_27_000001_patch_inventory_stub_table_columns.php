<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.3: the catch-all scaffold migration (database/migrations/
 * 2026_05_29_000003_create_all_missing_module_tables.php) created 26
 * inventory_* tables as bare id/tenant_id/status/data/timestamps stubs.
 * Later passes patched most of them to match their real models, but 7
 * were left behind — some backing live, routed endpoints that crash on
 * write today (CycleCountController::assign, PickingOrderController's
 * source_id, PurchaseOrderController's expected_at/receive()), others
 * orphaned (no controller yet — Lot/LotMovement's created_by,
 * PoReceiptLine, ReorderRule) but patched now to match their models'
 * already-declared $fillable rather than left as a landmine for
 * whichever chantier wires them up next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_cycle_counts', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_cycle_counts', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('created_by');
            }
        });

        Schema::table('inventory_lots', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_lots', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('notes');
            }
        });

        Schema::table('inventory_lot_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_lot_movements', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('performed_by');
            }
        });

        Schema::table('inventory_picking_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_picking_orders', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
        });

        Schema::table('inventory_po_receipt_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_po_receipt_lines', 'purchase_order_item_id')) {
                $table->unsignedBigInteger('purchase_order_item_id')->nullable()->after('po_item_id');
            }
            if (!Schema::hasColumn('inventory_po_receipt_lines', 'quantity_ordered')) {
                $table->decimal('quantity_ordered', 12, 4)->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('inventory_po_receipt_lines', 'quantity_received')) {
                $table->decimal('quantity_received', 12, 4)->nullable()->after('received_qty');
            }
        });

        Schema::table('inventory_purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_purchase_orders', 'expected_at')) {
                $table->timestamp('expected_at')->nullable()->after('expected_date');
            }
            if (!Schema::hasColumn('inventory_purchase_orders', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('sent_at');
            }
        });

        Schema::table('inventory_reorder_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_reorder_rules', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('inventory_reorder_rules', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('inventory_reorder_rules', 'min_level')) {
                $table->decimal('min_level', 12, 4)->nullable()->after('warehouse_id');
            }
            if (!Schema::hasColumn('inventory_reorder_rules', 'max_level')) {
                $table->decimal('max_level', 12, 4)->nullable()->after('min_level');
            }
            if (!Schema::hasColumn('inventory_reorder_rules', 'reorder_quantity')) {
                $table->decimal('reorder_quantity', 12, 4)->nullable()->after('max_level');
            }
            if (!Schema::hasColumn('inventory_reorder_rules', 'lead_time_days')) {
                $table->unsignedInteger('lead_time_days')->nullable()->after('reorder_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_cycle_counts', function (Blueprint $table) {
            $table->dropColumn('assigned_to');
        });
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
        Schema::table('inventory_lot_movements', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
        Schema::table('inventory_picking_orders', function (Blueprint $table) {
            $table->dropColumn('source_id');
        });
        Schema::table('inventory_po_receipt_lines', function (Blueprint $table) {
            $table->dropColumn(['purchase_order_item_id', 'quantity_ordered', 'quantity_received']);
        });
        Schema::table('inventory_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['expected_at', 'received_at']);
        });
        Schema::table('inventory_reorder_rules', function (Blueprint $table) {
            $table->dropColumn(['product_id', 'warehouse_id', 'min_level', 'max_level', 'reorder_quantity', 'lead_time_days']);
        });
    }
};
