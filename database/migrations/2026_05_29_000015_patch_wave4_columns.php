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
        // ── pos_table_sections (more columns) ─────────────────────────────
        $this->patch('pos_table_sections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
        });

        // ── pos_configs (more columns) ────────────────────────────────────
        $this->patch('pos_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'payment_methods'))  $t->text('payment_methods')->nullable();
        });

        // ── pos_payment_terminals (more columns) ──────────────────────────
        $this->patch('pos_payment_terminals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'serial_number'))  $t->string('serial_number')->nullable();
        });

        // ── pos_shifts (more columns) ─────────────────────────────────────
        $this->patch('pos_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'expected_cash'))  $t->decimal('expected_cash', 15, 4)->nullable();
        });

        // ── planning_shifts (more columns) ────────────────────────────────
        $this->patch('planning_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'duration_minutes'))  $t->integer('duration_minutes')->nullable();
        });

        // ── planning_schedule_conflicts (more columns) ────────────────────
        $this->patch('planning_schedule_conflicts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'conflict_date'))  $t->date('conflict_date')->nullable();
        });

        // ── prj_time_entries (more columns) ───────────────────────────────
        $this->patch('prj_time_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'started_at'))  $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'ended_at'))    $t->timestamp('ended_at')->nullable();
        });

        // ── prj_resource_allocations (more columns) ────────────────────────
        $this->patch('prj_resource_allocations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'hours_per_day'))  $t->decimal('hours_per_day', 5, 2)->nullable();
        });

        // ── prj_project_billing (more columns) ────────────────────────────
        $this->patch('prj_project_billing', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'hourly_rate'))  $t->decimal('hourly_rate', 15, 4)->nullable();
        });

        // ── prj_resource_capacity (more columns) ──────────────────────────
        $this->patch('prj_resource_capacity', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'date'))  $t->date('date')->nullable();
        });

        // ── prj_sprints ────────────────────────────────────────────────────
        $this->patch('prj_sprints', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))      $t->string('status', 20)->default('planning');
            if (!Schema::hasColumn($table, 'project_id'))  $t->unsignedBigInteger('project_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'goal'))        $t->text('goal')->nullable();
            if (!Schema::hasColumn($table, 'start_date'))  $t->date('start_date')->nullable();
            if (!Schema::hasColumn($table, 'end_date'))    $t->date('end_date')->nullable();
        });

        // ── project_task_dependencies ─────────────────────────────────────
        $this->patch('project_task_dependencies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'task_id'))      $t->unsignedBigInteger('task_id')->nullable();
            if (!Schema::hasColumn($table, 'depends_on'))   $t->unsignedBigInteger('depends_on')->nullable();
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 20)->default('finish_to_start');
        });

        // ── bi_kpi_alerts (more columns) ──────────────────────────────────
        $this->patch('bi_kpi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'condition'))  $t->string('condition', 20)->default('above');
        });

        // ── bi_predictive_models (more columns) ───────────────────────────
        $this->patch('bi_predictive_models', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'model_type'))  $t->string('model_type', 30)->nullable();
        });

        // ── bi_anomalies (more columns) ───────────────────────────────────
        $this->patch('bi_anomalies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'metric_name'))  $t->string('metric_name')->nullable();
        });

        // ── bi_scheduled_reports (more columns) ───────────────────────────
        $this->patch('bi_scheduled_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'next_send_at'))  $t->timestamp('next_send_at')->nullable();
        });

        // ── acc_consolidation_groups (more columns) ────────────────────────
        $this->patch('acc_consolidation_groups', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'consolidation_method'))  $t->string('consolidation_method', 30)->default('full');
        });

        // ── acc_fiscal_years (more columns) ───────────────────────────────
        $this->patch('acc_fiscal_years', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))  $t->string('status', 20)->default('open');
        });

        // ── quality_issues (more columns) ─────────────────────────────────
        $this->patch('quality_issues', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'inspection_id'))  $t->unsignedBigInteger('inspection_id')->nullable();
        });

        // ── quality_inspections (more columns) ────────────────────────────
        $this->patch('quality_inspections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'inspection_type'))  $t->string('inspection_type', 30)->nullable();
        });

        // ── timesheet_entries (more columns) ──────────────────────────────
        $this->patch('timesheet_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'entry_date'))  $t->date('entry_date')->nullable();
        });

        // ── mfg_capacity_constraints (more columns) ───────────────────────
        $this->patch('mfg_capacity_constraints', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'date'))  $t->date('date')->nullable();
        });

        // ── wa_chatbot_sessions (more columns) ────────────────────────────
        $this->patch('wa_chatbot_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_name'))  $t->string('contact_name')->nullable();
        });

        // ── tenants (more columns) ─────────────────────────────────────────
        $this->patch('tenants', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'domain'))  $t->string('domain')->nullable();
        });
    }

    public function down(): void {}
};
