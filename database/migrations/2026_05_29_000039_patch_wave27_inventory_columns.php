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
        // ── inventory_cycle_counts ────────────────────────────────────────
        $this->patch('inventory_cycle_counts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'count_date'))         $t->dateTime('count_date')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))       $t->timestamp('completed_at')->nullable();
        });

        // ── inventory_suppliers ───────────────────────────────────────────
        $this->patch('inventory_suppliers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'lead_time_days'))     $t->integer('lead_time_days')->default(7);
            if (!Schema::hasColumn($table, 'code'))               $t->string('code', 30)->nullable();
            if (!Schema::hasColumn($table, 'contact_name'))       $t->string('contact_name', 150)->nullable();
            if (!Schema::hasColumn($table, 'phone'))              $t->string('phone', 50)->nullable();
            if (!Schema::hasColumn($table, 'address'))            $t->text('address')->nullable();
            if (!Schema::hasColumn($table, 'country'))            $t->string('country', 5)->nullable();
            if (!Schema::hasColumn($table, 'website'))            $t->string('website', 300)->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'rating'))             $t->decimal('rating', 3, 1)->default(0);
        });

        // ── inventory_lots ────────────────────────────────────────────────
        $this->patch('inventory_lots', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
            if (!Schema::hasColumn($table, 'serial_number'))      $t->string('serial_number', 100)->nullable();
            if (!Schema::hasColumn($table, 'manufacture_date'))   $t->date('manufacture_date')->nullable();
            if (!Schema::hasColumn($table, 'expiry_date'))        $t->date('expiry_date')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))           $t->decimal('quantity', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('active');
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
        });

        // ── inventory_rmas ────────────────────────────────────────────────
        $this->patch('inventory_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'received_at'))        $t->timestamp('received_at')->nullable();
            if (!Schema::hasColumn($table, 'refunded_at'))        $t->timestamp('refunded_at')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))        $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'return_method'))      $t->string('return_method', 30)->nullable();
            if (!Schema::hasColumn($table, 'items'))              $t->text('items')->nullable();
        });

        // ── inventory_transfer_orders ─────────────────────────────────────
        $this->patch('inventory_transfer_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'expected_delivery_date')) $t->date('expected_delivery_date')->nullable();
            if (!Schema::hasColumn($table, 'notes'))              $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'total_items'))        $t->integer('total_items')->default(0);
            if (!Schema::hasColumn($table, 'total_value'))        $t->decimal('total_value', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'approved_by'))        $t->unsignedBigInteger('approved_by')->nullable();
        });

        // ── inventory_transfer_order_lines ────────────────────────────────
        $this->patch('inventory_transfer_order_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'transfer_order_id'))  $t->unsignedBigInteger('transfer_order_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'requested_qty'))      $t->decimal('requested_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'transferred_qty'))    $t->decimal('transferred_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
        });

        // ── inventory_picking_waves ───────────────────────────────────────
        $this->patch('inventory_picking_waves', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('draft');
            if (!Schema::hasColumn($table, 'wave_type'))          $t->string('wave_type', 30)->default('batch');
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'picker_id'))          $t->unsignedBigInteger('picker_id')->nullable();
            if (!Schema::hasColumn($table, 'started_at'))         $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))       $t->timestamp('completed_at')->nullable();
        });

        // ── inventory_picking_orders ──────────────────────────────────────
        $this->patch('inventory_picking_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'wave_id'))            $t->unsignedBigInteger('wave_id')->nullable();
            if (!Schema::hasColumn($table, 'order_id'))           $t->unsignedBigInteger('order_id')->nullable();
            if (!Schema::hasColumn($table, 'order_type'))         $t->string('order_type', 30)->default('sales');
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'priority'))           $t->integer('priority')->default(0);
        });

        // ── inventory_picking_lines ───────────────────────────────────────
        $this->patch('inventory_picking_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'picking_order_id'))   $t->unsignedBigInteger('picking_order_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'location_id'))        $t->unsignedBigInteger('location_id')->nullable();
            if (!Schema::hasColumn($table, 'requested_qty'))      $t->decimal('requested_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'picked_qty'))         $t->decimal('picked_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'lot_number'))         $t->string('lot_number', 100)->nullable();
        });

        // ── inventory_crossdock_operations ───────────────────────────────
        $this->patch('inventory_crossdock_operations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))          $t->string('reference', 50)->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'incoming_shipment_id')) $t->unsignedBigInteger('incoming_shipment_id')->nullable();
            if (!Schema::hasColumn($table, 'outgoing_order_id'))  $t->unsignedBigInteger('outgoing_order_id')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_id'))       $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'items'))              $t->text('items')->nullable();
        });

        // ── inventory_demand_forecasts ────────────────────────────────────
        $this->patch('inventory_demand_forecasts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))         $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'period'))             $t->string('period', 20)->nullable();
            if (!Schema::hasColumn($table, 'forecast_qty'))       $t->decimal('forecast_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'actual_qty'))         $t->decimal('actual_qty', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'accuracy'))           $t->decimal('accuracy', 5, 2)->nullable();
            if (!Schema::hasColumn($table, 'algorithm'))          $t->string('algorithm', 30)->default('moving_avg');
        });

        // ── inventory_redistribution_rules ────────────────────────────────
        $this->patch('inventory_redistribution_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))               $t->string('name', 200)->nullable();
            if (!Schema::hasColumn($table, 'source_warehouse_id')) $t->unsignedBigInteger('source_warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'target_warehouse_id')) $t->unsignedBigInteger('target_warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'trigger_condition'))  $t->string('trigger_condition', 50)->default('low_stock');
            if (!Schema::hasColumn($table, 'threshold'))          $t->decimal('threshold', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'is_active'))          $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void {}
};
