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
        $this->patch('mfg_operations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'routing_id'))     $t->unsignedBigInteger('routing_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'sequence'))       $t->integer('sequence')->default(0);
            if (!Schema::hasColumn($table, 'work_center_id')) $t->unsignedBigInteger('work_center_id')->nullable();
            if (!Schema::hasColumn($table, 'duration'))       $t->decimal('duration', 10, 2)->default(0);
            if (!Schema::hasColumn($table, 'setup_time'))     $t->decimal('setup_time', 10, 2)->default(0);
        });

        $this->patch('mfg_production_cost_records', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'production_order_id')) $t->unsignedBigInteger('production_order_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'cost_type'))           $t->string('cost_type')->nullable();
            if (!Schema::hasColumn($table, 'amount'))              $t->decimal('amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'currency'))            $t->string('currency', 3)->default('XOF');
            if (!Schema::hasColumn($table, 'recorded_at'))         $t->timestamp('recorded_at')->nullable();
        });

        $this->patch('mfg_material_consumptions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'planned_qty'))         $t->decimal('planned_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'actual_qty'))          $t->decimal('actual_qty', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'production_order_id')) $t->unsignedBigInteger('production_order_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'product_id'))          $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'unit'))                $t->string('unit')->nullable();
        });

        $this->patch('mfg_bom_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_critical'))         $t->boolean('is_critical')->default(false);
            if (!Schema::hasColumn($table, 'operation'))           $t->string('operation')->nullable();
            if (!Schema::hasColumn($table, 'scrap_percentage'))    $t->decimal('scrap_percentage', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'unit_cost'))           $t->decimal('unit_cost', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'notes'))               $t->text('notes')->nullable();
        });

        $this->patch('mfg_bottleneck_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'report_date'))              $t->date('report_date')->nullable();
            if (!Schema::hasColumn($table, 'analysis_period_days'))     $t->integer('analysis_period_days')->default(14);
            if (!Schema::hasColumn($table, 'bottleneck_workcenter_id')) $t->unsignedBigInteger('bottleneck_workcenter_id')->nullable();
            if (!Schema::hasColumn($table, 'utilization_data'))         $t->text('utilization_data')->nullable();
            if (!Schema::hasColumn($table, 'recommendations'))          $t->text('recommendations')->nullable();
            if (!Schema::hasColumn($table, 'generated_at'))             $t->timestamp('generated_at')->nullable();
        });

        $this->patch('mfg_capacity_allocations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes')) $t->text('notes')->nullable();
        });
    }

    public function down(): void {}
};
