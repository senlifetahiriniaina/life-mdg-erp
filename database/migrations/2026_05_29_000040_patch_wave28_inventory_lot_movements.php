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
        // ── inventory_lot_movements ───────────────────────────────────────
        $this->patch('inventory_lot_movements', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'lot_id'))             $t->unsignedBigInteger('lot_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'movement_type'))      $t->string('movement_type', 30)->default('receipt');
            if (!Schema::hasColumn($table, 'quantity'))           $t->decimal('quantity', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 100)->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_from_id'))  $t->unsignedBigInteger('warehouse_from_id')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_to_id'))    $t->unsignedBigInteger('warehouse_to_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'performed_by'))       $t->unsignedBigInteger('performed_by')->nullable();
        });

        // ── inventory_crossdock_operations (executed_at) ───────────────
        $this->patch('inventory_crossdock_operations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'executed_at'))        $t->timestamp('executed_at')->nullable();
        });

        // ── inventory_picking_lines (quantity_requested alias) ────────────
        $this->patch('inventory_picking_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity_requested'))  $t->decimal('quantity_requested', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'quantity_picked'))     $t->decimal('quantity_picked', 15, 4)->default(0);
        });

        // ── inventory_cycle_count_lines ────────────────────────────────
        $this->patch('inventory_cycle_count_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cycle_count_id'))     $t->unsignedBigInteger('cycle_count_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'location_id'))        $t->unsignedBigInteger('location_id')->nullable();
            if (!Schema::hasColumn($table, 'expected_qty'))       $t->decimal('expected_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'counted_qty'))        $t->decimal('counted_qty', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'variance'))           $t->decimal('variance', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
        });

        // ── inventory_shipments ───────────────────────────────────────────
        $this->patch('inventory_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 20)->default('outbound');
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'origin'))             $t->string('origin', 200)->nullable();
            if (!Schema::hasColumn($table, 'destination'))        $t->string('destination', 200)->nullable();
            if (!Schema::hasColumn($table, 'carrier_id'))         $t->unsignedBigInteger('carrier_id')->nullable();
            if (!Schema::hasColumn($table, 'tracking_number'))    $t->string('tracking_number', 100)->nullable();
            if (!Schema::hasColumn($table, 'shipped_at'))         $t->timestamp('shipped_at')->nullable();
            if (!Schema::hasColumn($table, 'delivered_at'))       $t->timestamp('delivered_at')->nullable();
            if (!Schema::hasColumn($table, 'estimated_delivery')) $t->date('estimated_delivery')->nullable();
            if (!Schema::hasColumn($table, 'weight'))             $t->decimal('weight', 10, 3)->nullable();
            if (!Schema::hasColumn($table, 'items'))              $t->text('items')->nullable();
        });

        // ── inventory_shipment_events ─────────────────────────────────────
        $this->patch('inventory_shipment_events', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipment_id'))        $t->unsignedBigInteger('shipment_id')->nullable();
            if (!Schema::hasColumn($table, 'event_type'))         $t->string('event_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'location'))           $t->string('location', 200)->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'occurred_at'))        $t->timestamp('occurred_at')->nullable();
        });
    }

    public function down(): void {}
};
