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

    public function up(): void
    {
        // ── bi_scheduled_reports ──────────────────────────────────────────
        $this->patch('bi_scheduled_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'report_id'))   $t->unsignedBigInteger('report_id')->nullable();
            if (!Schema::hasColumn($table, 'schedule'))    $t->string('schedule')->nullable();
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'recipients'))  $t->text('recipients')->nullable();
            if (!Schema::hasColumn($table, 'format'))      $t->string('format', 10)->default('pdf');
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'last_run_at')) $t->timestamp('last_run_at')->nullable();
        });

        // ── bi_dashboards ─────────────────────────────────────────────────
        $this->patch('bi_dashboards', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_id'))     $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'layout'))      $t->text('layout')->nullable();
            if (!Schema::hasColumn($table, 'is_default'))  $t->boolean('is_default')->default(false);
        });

        // ── bi_data_sources ───────────────────────────────────────────────
        $this->patch('bi_data_sources', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))        $t->string('type', 20)->nullable();
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'connection'))  $t->text('connection')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
        });

        // ── bi_kpis (more columns) ────────────────────────────────────────
        $this->patch('bi_kpis', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'source_module'))  $t->string('source_module')->nullable();
            if (!Schema::hasColumn($table, 'name'))           $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'formula'))        $t->text('formula')->nullable();
            if (!Schema::hasColumn($table, 'unit'))           $t->string('unit', 20)->nullable();
            if (!Schema::hasColumn($table, 'target'))         $t->decimal('target', 15, 4)->nullable();
        });

        // ── bi_kpi_alerts (more columns) ──────────────────────────────────
        $this->patch('bi_kpi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'metric_name'))  $t->string('metric_name')->nullable();
        });

        // ── bi_alerts ─────────────────────────────────────────────────────
        $this->patch('bi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'condition_type'))  $t->string('condition_type', 20)->default('threshold');
            if (!Schema::hasColumn($table, 'name'))            $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'metric'))          $t->string('metric')->nullable();
            if (!Schema::hasColumn($table, 'threshold'))       $t->decimal('threshold', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))       $t->boolean('is_active')->default(true);
        });

        // ── planning_schedule_conflicts ────────────────────────────────────
        $this->patch('planning_schedule_conflicts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'employee_id'))  $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'shift_id_1'))   $t->unsignedBigInteger('shift_id_1')->nullable();
            if (!Schema::hasColumn($table, 'shift_id_2'))   $t->unsignedBigInteger('shift_id_2')->nullable();
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 30)->nullable();
            if (!Schema::hasColumn($table, 'resolved'))     $t->boolean('resolved')->default(false);
        });

        // ── timesheet_entries ─────────────────────────────────────────────
        $this->patch('timesheet_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'employee_id'))  $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'date'))         $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'project_id'))   $t->unsignedBigInteger('project_id')->nullable();
            if (!Schema::hasColumn($table, 'task_id'))      $t->unsignedBigInteger('task_id')->nullable();
            if (!Schema::hasColumn($table, 'hours'))        $t->decimal('hours', 6, 2)->default(0);
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'billable'))     $t->boolean('billable')->default(false);
        });

        // ── mfg_capacity_constraints ──────────────────────────────────────
        $this->patch('mfg_capacity_constraints', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workcenter_id'))     $t->unsignedBigInteger('workcenter_id')->nullable();
            if (!Schema::hasColumn($table, 'constraint_type'))   $t->string('constraint_type', 30)->nullable();
            if (!Schema::hasColumn($table, 'start_date'))        $t->date('start_date')->nullable();
            if (!Schema::hasColumn($table, 'end_date'))          $t->date('end_date')->nullable();
            if (!Schema::hasColumn($table, 'capacity_factor'))   $t->decimal('capacity_factor', 5, 2)->default(1);
            if (!Schema::hasColumn($table, 'notes'))             $t->text('notes')->nullable();
        });

        // ── achats_suppliers (more columns) ───────────────────────────────
        $this->patch('achats_suppliers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by'))  $t->unsignedBigInteger('created_by')->nullable();
        });
    }

    public function down(): void {}
};
