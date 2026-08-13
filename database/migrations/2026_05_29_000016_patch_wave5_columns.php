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
        // ── prj_time_entries (more columns) ───────────────────────────────
        $this->patch('prj_time_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'duration_minutes'))  $t->integer('duration_minutes')->nullable();
            if (!Schema::hasColumn($table, 'hourly_rate'))       $t->decimal('hourly_rate', 15, 4)->nullable();
        });

        // ── prj_resource_allocations (more columns) ────────────────────────
        $this->patch('prj_resource_allocations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'actual_hours_logged'))  $t->decimal('actual_hours_logged', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'status'))               $t->string('status', 20)->default('active');
        });

        // ── prj_project_billing (more columns) ────────────────────────────
        $this->patch('prj_project_billing', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'budget_hours'))  $t->decimal('budget_hours', 8, 2)->nullable();
        });

        // ── prj_resource_capacity (more columns) ──────────────────────────
        $this->patch('prj_resource_capacity', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'available_hours'))  $t->decimal('available_hours', 6, 2)->nullable();
        });

        // ── prj_sprints (more columns) ─────────────────────────────────────
        $this->patch('prj_sprints', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'capacity_points'))  $t->integer('capacity_points')->nullable();
        });

        // ── project_task_dependencies (more columns) ──────────────────────
        $this->patch('project_task_dependencies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'depends_on_task_id'))  $t->unsignedBigInteger('depends_on_task_id')->nullable();
        });

        // ── prj_team_members ──────────────────────────────────────────────
        $this->patch('prj_team_members', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'left_at'))     $t->timestamp('left_at')->nullable();
            if (!Schema::hasColumn($table, 'project_id'))  $t->unsignedBigInteger('project_id')->nullable();
            if (!Schema::hasColumn($table, 'user_id'))     $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'role'))        $t->string('role', 30)->default('member');
            if (!Schema::hasColumn($table, 'joined_at'))   $t->timestamp('joined_at')->nullable();
        });

        // ── prj_epics ─────────────────────────────────────────────────────
        $this->patch('prj_epics', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))       $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'project_id'))  $t->unsignedBigInteger('project_id')->nullable();
            if (!Schema::hasColumn($table, 'description')) $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'start_date'))  $t->date('start_date')->nullable();
            if (!Schema::hasColumn($table, 'end_date'))    $t->date('end_date')->nullable();
        });

        // ── prj_automation_rules ──────────────────────────────────────────
        $this->patch('prj_automation_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'project_id'))  $t->unsignedBigInteger('project_id')->nullable();
            if (!Schema::hasColumn($table, 'trigger'))     $t->string('trigger')->nullable();
            if (!Schema::hasColumn($table, 'action'))      $t->text('action')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
        });

        // ── quality_issues (more columns) ─────────────────────────────────
        $this->patch('quality_issues', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'issue_type'))  $t->string('issue_type', 30)->nullable();
        });

        // ── quality_inspections (more columns) ────────────────────────────
        $this->patch('quality_inspections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity_inspected'))  $t->decimal('quantity_inspected', 15, 4)->nullable();
        });

        // ── achats_suppliers (more columns) ───────────────────────────────
        $this->patch('achats_suppliers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'lead_time_days'))  $t->integer('lead_time_days')->nullable();
        });

        // ── achats_rfqs ───────────────────────────────────────────────────
        $this->patch('achats_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'required_by_date'))  $t->date('required_by_date')->nullable();
        });

        // ── mfg_work_orders (more columns) ────────────────────────────────
        $this->patch('mfg_work_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'warehouse_id'))  $t->unsignedBigInteger('warehouse_id')->nullable();
        });

        // ── mfg_production_orders (more columns) ──────────────────────────
        $this->patch('mfg_production_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deadline'))  $t->date('deadline')->nullable();
        });

        // ── acc_reconciliations (more columns) ────────────────────────────
        $this->patch('acc_reconciliations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'system_balance'))  $t->decimal('system_balance', 15, 4)->default(0);
        });

        // ── acc_intercompany_transactions (more columns) ───────────────────
        $this->patch('acc_intercompany_transactions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'eliminated_at'))  $t->timestamp('eliminated_at')->nullable();
        });
    }

    public function down(): void {}
};
