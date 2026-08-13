<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    private function addCol(string $table, string $column, \Closure $def): void
    {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $t) use ($column, $def) {
                $def($t);
            });
        }
    }

    public function up(): void
    {
        // ── mfg_workcenters ────────────────────────────────────────────────
        $this->patch('mfg_workcenters', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'currency'))      $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'cost_per_hour')) $t->decimal('cost_per_hour', 15, 4)->default(0);
        });

        // ── mfg_bom_lines ──────────────────────────────────────────────────
        $this->patch('mfg_bom_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'component_product_id')) $t->unsignedBigInteger('component_product_id')->nullable();
            if (!Schema::hasColumn($table, 'bom_id'))               $t->unsignedBigInteger('bom_id')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))             $t->decimal('quantity', 15, 4)->default(1);
            if (!Schema::hasColumn($table, 'unit_cost'))            $t->decimal('unit_cost', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'scrap_rate'))           $t->decimal('scrap_rate', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'uom'))                  $t->string('uom', 20)->nullable();
            if (!Schema::hasColumn($table, 'notes'))                $t->text('notes')->nullable();
        });

        // ── mfg_mrp_runs ──────────────────────────────────────────────────
        $this->patch('mfg_mrp_runs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))                  $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'run_at'))                $t->timestamp('run_at')->nullable();
            if (!Schema::hasColumn($table, 'planning_horizon_days')) $t->integer('planning_horizon_days')->default(30);
            if (!Schema::hasColumn($table, 'demand_data'))           $t->text('demand_data')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))            $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'results'))               $t->text('results')->nullable();
        });

        // ── mfg_routings ──────────────────────────────────────────────────
        $this->patch('mfg_routings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'code'))        $t->string('code')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))  $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'notes'))       $t->text('notes')->nullable();
        });

        // ── mfg_standard_costs ────────────────────────────────────────────
        $this->patch('mfg_standard_costs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))      $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'effective_date'))  $t->date('effective_date')->nullable();
            if (!Schema::hasColumn($table, 'material_cost'))   $t->decimal('material_cost', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'labor_cost'))      $t->decimal('labor_cost', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'overhead_cost'))   $t->decimal('overhead_cost', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'total_cost'))      $t->decimal('total_cost', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'currency'))        $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'set_by'))          $t->unsignedBigInteger('set_by')->nullable();
        });

        // ── mfg_iot_devices ───────────────────────────────────────────────
        $this->patch('mfg_iot_devices', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))       $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'device_id'))  $t->string('device_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))       $t->string('type')->nullable();
            if (!Schema::hasColumn($table, 'api_key'))    $t->string('api_key', 512)->nullable();
            if (!Schema::hasColumn($table, 'workcenter_id')) $t->unsignedBigInteger('workcenter_id')->nullable();
            if (!Schema::hasColumn($table, 'last_ping_at')) $t->timestamp('last_ping_at')->nullable();
        });

        // ── inventory_cost_layers (add missing columns) ───────────────────
        $this->patch('inventory_cost_layers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'method'))              $t->string('method', 20)->default('fifo');
            if (!Schema::hasColumn($table, 'quantity_received'))   $t->decimal('quantity_received', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'quantity_remaining'))  $t->decimal('quantity_remaining', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'total_cost'))          $t->decimal('total_cost', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'received_at'))         $t->timestamp('received_at')->nullable();
            if (!Schema::hasColumn($table, 'reference'))           $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'is_exhausted'))        $t->boolean('is_exhausted')->default(false);
        });

        // ── inventory_demand_forecasts (more columns) ──────────────────────
        $this->patch('inventory_demand_forecasts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'warehouse_id'))    $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'period_start'))    $t->timestamp('period_start')->nullable();
            if (!Schema::hasColumn($table, 'period_end'))      $t->timestamp('period_end')->nullable();
            if (!Schema::hasColumn($table, 'period_type'))     $t->string('period_type', 20)->default('monthly');
            if (!Schema::hasColumn($table, 'forecasted_qty'))  $t->decimal('forecasted_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'method'))          $t->string('method', 30)->nullable();
            if (!Schema::hasColumn($table, 'confidence'))      $t->decimal('confidence', 5, 2)->nullable();
            if (!Schema::hasColumn($table, 'metadata'))        $t->text('metadata')->nullable();
        });

        // ── inventory_seasonal_factors ────────────────────────────────────
        $this->patch('inventory_seasonal_factors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))   $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'category_id'))  $t->unsignedBigInteger('category_id')->nullable();
            if (!Schema::hasColumn($table, 'period_type'))  $t->string('period_type', 20)->default('monthly');
            if (!Schema::hasColumn($table, 'period_index')) $t->integer('period_index')->default(1);
            if (!Schema::hasColumn($table, 'factor'))       $t->decimal('factor', 8, 4)->default(1);
            if (!Schema::hasColumn($table, 'notes'))        $t->text('notes')->nullable();
        });

        // ── inventory_suppliers ───────────────────────────────────────────
        $this->patch('inventory_suppliers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'email'))         $t->string('email')->nullable();
            if (!Schema::hasColumn($table, 'currency'))      $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'payment_terms')) $t->string('payment_terms')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))     $t->boolean('is_active')->default(true);
        });

        // ── inventory_shipments ───────────────────────────────────────────
        $this->patch('inventory_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'carrier_id'))             $t->unsignedBigInteger('carrier_id')->nullable();
            if (!Schema::hasColumn($table, 'reference'))              $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'order_id'))               $t->unsignedBigInteger('order_id')->nullable();
            if (!Schema::hasColumn($table, 'tracking_number'))        $t->string('tracking_number')->nullable();
            if (!Schema::hasColumn($table, 'label_url'))              $t->text('label_url')->nullable();
            if (!Schema::hasColumn($table, 'origin_address'))         $t->text('origin_address')->nullable();
            if (!Schema::hasColumn($table, 'destination_address'))    $t->text('destination_address')->nullable();
            if (!Schema::hasColumn($table, 'weight_kg'))              $t->decimal('weight_kg', 10, 3)->nullable();
            if (!Schema::hasColumn($table, 'dimensions'))             $t->text('dimensions')->nullable();
            if (!Schema::hasColumn($table, 'service_type'))           $t->string('service_type')->nullable();
            if (!Schema::hasColumn($table, 'estimated_cost'))         $t->decimal('estimated_cost', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'actual_cost'))            $t->decimal('actual_cost', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'shipped_at'))             $t->timestamp('shipped_at')->nullable();
            if (!Schema::hasColumn($table, 'estimated_delivery_at'))  $t->timestamp('estimated_delivery_at')->nullable();
            if (!Schema::hasColumn($table, 'delivered_at'))           $t->timestamp('delivered_at')->nullable();
        });

        // ── inventory_rmas ────────────────────────────────────────────────
        $this->patch('inventory_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))     $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'order_id'))      $t->unsignedBigInteger('order_id')->nullable();
            if (!Schema::hasColumn($table, 'customer_name')) $t->string('customer_name')->nullable();
            if (!Schema::hasColumn($table, 'reason'))        $t->text('reason')->nullable();
            if (!Schema::hasColumn($table, 'items'))         $t->text('items')->nullable();
            if (!Schema::hasColumn($table, 'return_method')) $t->string('return_method')->nullable();
        });

        // ── inventory_picking_orders ──────────────────────────────────────
        $this->patch('inventory_picking_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))    $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_id')) $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))         $t->string('type')->nullable();
            if (!Schema::hasColumn($table, 'source_type'))  $t->string('source_type')->nullable();
            if (!Schema::hasColumn($table, 'assigned_to'))  $t->unsignedBigInteger('assigned_to')->nullable();
            if (!Schema::hasColumn($table, 'completed_at')) $t->timestamp('completed_at')->nullable();
        });

        // ── inventory_picking_waves ───────────────────────────────────────
        $this->patch('inventory_picking_waves', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'order_ids'))    $t->text('order_ids')->nullable();
            if (!Schema::hasColumn($table, 'assigned_to'))  $t->unsignedBigInteger('assigned_to')->nullable();
            if (!Schema::hasColumn($table, 'completed_at')) $t->timestamp('completed_at')->nullable();
        });

        // ── inventory_cycle_count_lines ───────────────────────────────────
        $this->patch('inventory_cycle_count_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))      $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'location_id'))     $t->unsignedBigInteger('location_id')->nullable();
            if (!Schema::hasColumn($table, 'system_qty'))      $t->decimal('system_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'counted_qty'))     $t->decimal('counted_qty', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'cycle_count_id'))  $t->unsignedBigInteger('cycle_count_id')->nullable();
            if (!Schema::hasColumn($table, 'variance'))        $t->decimal('variance', 15, 4)->nullable();
        });

        // ── inventory_crossdock_operations ────────────────────────────────
        $this->patch('inventory_crossdock_operations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'inbound_shipment_id'))  $t->unsignedBigInteger('inbound_shipment_id')->nullable();
            if (!Schema::hasColumn($table, 'outbound_order_id'))    $t->unsignedBigInteger('outbound_order_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))           $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'qty'))                  $t->decimal('qty', 15, 4)->default(0);
        });
    }

    public function down(): void {}
};
