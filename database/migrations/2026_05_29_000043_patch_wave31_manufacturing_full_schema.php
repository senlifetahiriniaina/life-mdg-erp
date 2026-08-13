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
        // mfg_outsourced_orders: add missing columns
        $this->patch('mfg_outsourced_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'production_order_id')) $t->unsignedBigInteger('production_order_id')->nullable();
            if (!Schema::hasColumn($table, 'subcontractor_id')) $t->unsignedBigInteger('subcontractor_id')->nullable();
            if (!Schema::hasColumn($table, 'reference')) $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'status')) $t->string('status')->default('draft');
            if (!Schema::hasColumn($table, 'quantity')) $t->decimal('quantity', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'unit_cost')) $t->decimal('unit_cost', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'total_cost')) $t->decimal('total_cost', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'due_date')) $t->date('due_date')->nullable();
            if (!Schema::hasColumn($table, 'sent_date')) $t->timestamp('sent_date')->nullable();
            if (!Schema::hasColumn($table, 'received_date')) $t->timestamp('received_date')->nullable();
            if (!Schema::hasColumn($table, 'quality_result')) $t->string('quality_result')->nullable();
            if (!Schema::hasColumn($table, 'quality_notes')) $t->text('quality_notes')->nullable();
            if (!Schema::hasColumn($table, 'invoice_id')) $t->unsignedBigInteger('invoice_id')->nullable();
            if (!Schema::hasColumn($table, 'payment_status')) $t->string('payment_status')->default('pending');
            if (!Schema::hasColumn($table, 'paid_at')) $t->timestamp('paid_at')->nullable();
            if (!Schema::hasColumn($table, 'internal_notes')) $t->text('internal_notes')->nullable();
        });

        // mfg_quality_checks: add missing columns
        $this->patch('mfg_quality_checks', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'work_order_id')) $t->unsignedBigInteger('work_order_id')->nullable();
            if (!Schema::hasColumn($table, 'quantity_passed')) $t->decimal('quantity_passed', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'quantity_failed')) $t->decimal('quantity_failed', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'quantity_rejected')) $t->decimal('quantity_rejected', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'quantity_checked')) $t->decimal('quantity_checked', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'check_type')) $t->string('check_type')->nullable();
        });

        // mfg_operation_resources: add operation_id if missing
        $this->patch('mfg_operation_resources', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'operation_id')) $t->unsignedBigInteger('operation_id')->nullable();
        });

        // mfg_traceability_events: add lot_id if missing
        $this->patch('mfg_traceability_events', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'lot_id')) $t->unsignedBigInteger('lot_id')->nullable();
            if (!Schema::hasColumn($table, 'serial_id')) $t->unsignedBigInteger('serial_id')->nullable();
            if (!Schema::hasColumn($table, 'from_location')) $t->string('from_location')->nullable();
            if (!Schema::hasColumn($table, 'to_location')) $t->string('to_location')->nullable();
            if (!Schema::hasColumn($table, 'quantity')) $t->decimal('quantity', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'reference_type')) $t->string('reference_type')->nullable();
            if (!Schema::hasColumn($table, 'reference_id')) $t->unsignedBigInteger('reference_id')->nullable();
            if (!Schema::hasColumn($table, 'performed_by')) $t->unsignedBigInteger('performed_by')->nullable();
            if (!Schema::hasColumn($table, 'performed_at')) $t->timestamp('performed_at')->nullable();
            if (!Schema::hasColumn($table, 'metadata')) $t->json('metadata')->nullable();
        });

        // mfg_work_order_materials: add material_id if missing
        $this->patch('mfg_work_order_materials', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'work_order_id')) $t->unsignedBigInteger('work_order_id')->nullable();
            if (!Schema::hasColumn($table, 'material_id')) $t->unsignedBigInteger('material_id')->nullable();
            if (!Schema::hasColumn($table, 'material_name')) $t->string('material_name')->nullable();
            if (!Schema::hasColumn($table, 'quantity_required')) $t->decimal('quantity_required', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'quantity_used')) $t->decimal('quantity_used', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'unit')) $t->string('unit', 50)->nullable();
        });

        // mfg_operations: add name if missing
        $this->patch('mfg_operations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name')) $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'sequence')) $t->unsignedSmallInteger('sequence')->default(10);
            if (!Schema::hasColumn($table, 'duration_minutes')) $t->unsignedInteger('duration_minutes')->default(60);
            if (!Schema::hasColumn($table, 'setup_time_minutes')) $t->unsignedInteger('setup_time_minutes')->default(0);
            if (!Schema::hasColumn($table, 'cost_per_hour')) $t->decimal('cost_per_hour', 10, 2)->default(0);
            if (!Schema::hasColumn($table, 'description')) $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'active')) $t->boolean('active')->default(true);
        });

        // mfg_routings: add duration_minutes, name, code, status, sequence
        $this->patch('mfg_routings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'duration_minutes')) $t->integer('duration_minutes')->default(0);
            if (!Schema::hasColumn($table, 'sequence')) $t->integer('sequence')->default(1);
            if (!Schema::hasColumn($table, 'name')) $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'code')) $t->string('code')->nullable();
            if (!Schema::hasColumn($table, 'status')) $t->string('status')->default('active');
        });

        // mfg_lot_numbers: add lot_number (may exist as unique key but column missing)
        $this->patch('mfg_lot_numbers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'lot_number')) $t->string('lot_number')->nullable();
        });

        // mfg_subcontractors: add missing columns
        $this->patch('mfg_subcontractors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'city')) $t->string('city', 100)->nullable();
            if (!Schema::hasColumn($table, 'country')) $t->string('country', 100)->nullable();
            if (!Schema::hasColumn($table, 'capabilities')) $t->json('capabilities')->nullable();
            if (!Schema::hasColumn($table, 'lead_time_days')) $t->integer('lead_time_days')->default(5);
            if (!Schema::hasColumn($table, 'quality_rating')) $t->decimal('quality_rating', 3, 2)->default(0);
            if (!Schema::hasColumn($table, 'cost_multiplier')) $t->decimal('cost_multiplier', 5, 2)->default(1.0);
            if (!Schema::hasColumn($table, 'payment_terms')) $t->string('payment_terms')->nullable();
            if (!Schema::hasColumn($table, 'is_certified')) $t->boolean('is_certified')->default(false);
            if (!Schema::hasColumn($table, 'internal_notes')) $t->text('internal_notes')->nullable();
        });

        // mfg_subcontracts: add missing columns
        $this->patch('mfg_subcontracts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'component_product')) $t->string('component_product')->nullable();
            if (!Schema::hasColumn($table, 'qty')) $t->decimal('qty', 10, 2)->default(0);
            if (!Schema::hasColumn($table, 'unit_cost')) $t->decimal('unit_cost', 10, 2)->default(0);
            if (!Schema::hasColumn($table, 'total_cost')) $t->decimal('total_cost', 10, 2)->default(0);
            if (!Schema::hasColumn($table, 'sent_at')) $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'expected_delivery')) $t->date('expected_delivery')->nullable();
            if (!Schema::hasColumn($table, 'received_at')) $t->timestamp('received_at')->nullable();
        });

        // mfg_iot_readings: add device_id, metric_name, value, unit, recorded_at if missing
        $this->patch('mfg_iot_readings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'device_id')) $t->unsignedBigInteger('device_id')->nullable();
            if (!Schema::hasColumn($table, 'metric_name')) $t->string('metric_name')->nullable();
            if (!Schema::hasColumn($table, 'value')) $t->decimal('value', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'unit')) $t->string('unit')->nullable();
            if (!Schema::hasColumn($table, 'recorded_at')) $t->dateTime('recorded_at')->nullable();
        });

        // mfg_work_orders: add missing columns expected by factory/tests
        $this->patch('mfg_work_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes')) $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'sequence')) $t->integer('sequence')->default(1);
            if (!Schema::hasColumn($table, 'routing_id')) $t->unsignedBigInteger('routing_id')->nullable();
            if (!Schema::hasColumn($table, 'scheduled_start')) $t->timestamp('scheduled_start')->nullable();
            if (!Schema::hasColumn($table, 'scheduled_end')) $t->timestamp('scheduled_end')->nullable();
            if (!Schema::hasColumn($table, 'duration_minutes')) $t->integer('duration_minutes')->nullable();
            if (!Schema::hasColumn($table, 'product_id')) $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'bom_id')) $t->unsignedBigInteger('bom_id')->nullable();
            if (!Schema::hasColumn($table, 'warehouse_id')) $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'quantity_planned')) $t->decimal('quantity_planned', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'quantity_produced')) $t->decimal('quantity_produced', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'priority')) $t->string('priority')->default('normal');
            if (!Schema::hasColumn($table, 'created_by')) $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'operator_id')) $t->unsignedBigInteger('operator_id')->nullable();
            if (!Schema::hasColumn($table, 'actual_start')) $t->timestamp('actual_start')->nullable();
            if (!Schema::hasColumn($table, 'actual_end')) $t->timestamp('actual_end')->nullable();
            if (!Schema::hasColumn($table, 'setup_time_minutes')) $t->integer('setup_time_minutes')->default(0);
            if (!Schema::hasColumn($table, 'runtime_minutes')) $t->integer('runtime_minutes')->default(0);
            if (!Schema::hasColumn($table, 'scrap_quantity')) $t->decimal('scrap_quantity', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'yield_percent')) $t->decimal('yield_percent', 5, 2)->default(100);
            if (!Schema::hasColumn($table, 'quality_status')) $t->string('quality_status')->default('pending');
            if (!Schema::hasColumn($table, 'quality_notes')) $t->text('quality_notes')->nullable();
            if (!Schema::hasColumn($table, 'internal_notes')) $t->text('internal_notes')->nullable();
        });
    }

    public function down(): void {}
};
