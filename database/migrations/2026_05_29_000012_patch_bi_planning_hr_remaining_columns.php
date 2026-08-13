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
        // ── bi_kpi_alerts ─────────────────────────────────────────────────
        $this->patch('bi_kpi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'kpi_id'))         $t->unsignedBigInteger('kpi_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))           $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'metric'))         $t->string('metric')->nullable();
            if (!Schema::hasColumn($table, 'threshold'))      $t->decimal('threshold', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'operator'))       $t->string('operator', 10)->default('>=');
            if (!Schema::hasColumn($table, 'is_active'))      $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'triggered_at'))   $t->timestamp('triggered_at')->nullable();
        });

        // ── bi_anomalies ──────────────────────────────────────────────────
        $this->patch('bi_anomalies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'entity_type'))   $t->string('entity_type')->nullable();
            if (!Schema::hasColumn($table, 'entity_id'))     $t->unsignedBigInteger('entity_id')->nullable();
            if (!Schema::hasColumn($table, 'metric'))        $t->string('metric')->nullable();
            if (!Schema::hasColumn($table, 'expected'))      $t->decimal('expected', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'actual'))        $t->decimal('actual', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'severity'))      $t->string('severity', 20)->default('medium');
            if (!Schema::hasColumn($table, 'detected_at'))   $t->timestamp('detected_at')->nullable();
        });

        // ── planning_shifts ───────────────────────────────────────────────
        $this->patch('planning_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'code'))         $t->string('code')->nullable();
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'start_time'))   $t->time('start_time')->nullable();
            if (!Schema::hasColumn($table, 'end_time'))     $t->time('end_time')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });

        // ── hr_performance_cycles (more columns) ──────────────────────────
        $this->patch('hr_performance_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))  $t->string('type', 20)->default('annual');
        });

        // ── hr_attendance (different table from hr_attendance_records) ─────
        $this->patch('hr_attendance', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'employee_id'))  $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'date'))         $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'clock_in'))     $t->timestamp('clock_in')->nullable();
            if (!Schema::hasColumn($table, 'clock_out'))    $t->timestamp('clock_out')->nullable();
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 20)->default('regular');
            if (!Schema::hasColumn($table, 'ip_address'))   $t->string('ip_address')->nullable();
        });

        // ── hr_courses (more columns) ─────────────────────────────────────
        $this->patch('hr_courses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'duration_hours'))  $t->integer('duration_hours')->nullable();
        });

        // ── hr_salary_bands (more columns) ────────────────────────────────
        $this->patch('hr_salary_bands', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'mid_salary'))  $t->decimal('mid_salary', 15, 4)->nullable();
        });

        // ── hr_recruitment_jobs (more columns) ────────────────────────────
        $this->patch('hr_recruitment_jobs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'job_position_id'))    $t->unsignedBigInteger('job_position_id')->nullable();
            if (!Schema::hasColumn($table, 'positions_available')) $t->integer('positions_available')->default(1);
        });

        // ── hr_employee_skills ────────────────────────────────────────────
        $this->patch('hr_employee_skills', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'employee_id'))  $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'skill_id'))     $t->unsignedBigInteger('skill_id')->nullable();
            if (!Schema::hasColumn($table, 'level'))        $t->tinyInteger('level')->default(1);
            if (!Schema::hasColumn($table, 'certified_at')) $t->timestamp('certified_at')->nullable();
        });

        // ── hd_sla_breaches ───────────────────────────────────────────────
        $this->patch('hd_sla_breaches', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'ticket_id'))    $t->unsignedBigInteger('ticket_id')->nullable();
            if (!Schema::hasColumn($table, 'sla_type'))     $t->string('sla_type', 20)->default('response');
            if (!Schema::hasColumn($table, 'breached_at'))  $t->timestamp('breached_at')->nullable();
        });

        // ── inventory_lots (more columns) ─────────────────────────────────
        $this->patch('inventory_lots', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'serial_number'))  $t->string('serial_number')->nullable();
        });

        // ── hr_leave_requests ─────────────────────────────────────────────
        $this->patch('hr_leave_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'leave_type'))  $t->string('leave_type')->nullable();
        });

        // ── prj_resource_allocations (more columns) ────────────────────────
        $this->patch('prj_resource_allocations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'allocation_percent'))  $t->decimal('allocation_percent', 5, 2)->default(100);
        });

        // ── mfg_production_orders ─────────────────────────────────────────
        $this->patch('mfg_production_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'warehouse_id'))  $t->unsignedBigInteger('warehouse_id')->nullable();
        });
    }

    public function down(): void {}
};
