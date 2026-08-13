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
        // ── planning_shifts (more) ────────────────────────────────────────
        $this->patch('planning_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shift_type'))  $t->string('shift_type', 20)->default('regular');
        });

        // ── planning_schedule_conflicts (more) ────────────────────────────
        $this->patch('planning_schedule_conflicts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'conflict_type'))  $t->string('conflict_type', 30)->nullable();
        });

        // ── pos_tables ────────────────────────────────────────────────────
        $this->patch('pos_tables', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'section_id'))  $t->unsignedBigInteger('section_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'seats'))       $t->integer('seats')->default(4);
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
        });

        // ── pos_configs (more) ────────────────────────────────────────────
        $this->patch('pos_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'receipt_printer'))  $t->string('receipt_printer')->nullable();
        });

        // ── prj_time_entries (more) ───────────────────────────────────────
        $this->patch('prj_time_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'billed'))  $t->boolean('billed')->default(false);
        });

        // ── prj_resource_allocations (more) ───────────────────────────────
        $this->patch('prj_resource_allocations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes'))  $t->text('notes')->nullable();
        });

        // ── prj_project_billing (more) ────────────────────────────────────
        $this->patch('prj_project_billing', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'budget_amount'))  $t->decimal('budget_amount', 15, 4)->nullable();
        });

        // ── prj_resource_capacity (more) ──────────────────────────────────
        $this->patch('prj_resource_capacity', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_holiday'))  $t->boolean('is_holiday')->default(false);
        });

        // ── bi_kpi_alerts (more) ──────────────────────────────────────────
        $this->patch('bi_kpi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'comparison_value'))  $t->decimal('comparison_value', 15, 4)->nullable();
        });

        // ── bi_predictive_models (more) ───────────────────────────────────
        $this->patch('bi_predictive_models', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'training_data'))  $t->text('training_data')->nullable();
        });

        // ── bi_anomalies (more) ───────────────────────────────────────────
        $this->patch('bi_anomalies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'anomaly_date'))  $t->date('anomaly_date')->nullable();
        });

        // ── bi_scheduled_reports (more) ───────────────────────────────────
        $this->patch('bi_scheduled_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'send_count'))  $t->integer('send_count')->default(0);
        });

        // ── acc_consolidation_entities (more) ─────────────────────────────
        $this->patch('acc_consolidation_entities', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'entity_id'))  $t->unsignedBigInteger('entity_id')->nullable();
        });

        // ── acc_journal_entries (more) ────────────────────────────────────
        $this->patch('acc_journal_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))  $t->string('reference')->nullable();
        });

        // ── quality_issues (more) ─────────────────────────────────────────
        $this->patch('quality_issues', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity_affected'))  $t->decimal('quantity_affected', 15, 4)->nullable();
        });

        // ── quality_inspections (more) ────────────────────────────────────
        $this->patch('quality_inspections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity_accepted'))  $t->decimal('quantity_accepted', 15, 4)->nullable();
        });

        // ── timesheet_entries (more) ──────────────────────────────────────
        $this->patch('timesheet_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'hours_worked'))  $t->decimal('hours_worked', 6, 2)->default(0);
        });

        // ── hd_kb_articles (more) ─────────────────────────────────────────
        $this->patch('hd_kb_articles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'not_helpful_count'))  $t->integer('not_helpful_count')->default(0);
        });

        // ── hd_sla_breaches (more) ────────────────────────────────────────
        $this->patch('hd_sla_breaches', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'acknowledged_at'))  $t->timestamp('acknowledged_at')->nullable();
            if (!Schema::hasColumn($table, 'breach_minutes'))   $t->integer('breach_minutes')->nullable();
        });

        // ── hd_chat_sessions (more) ───────────────────────────────────────
        $this->patch('hd_chat_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'assigned_agent_id'))  $t->unsignedBigInteger('assigned_agent_id')->nullable();
        });

        // ── hd_chat_messages ─────────────────────────────────────────────
        $this->patch('hd_chat_messages', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sender_type'))    $t->string('sender_type', 20)->default('agent');
            if (!Schema::hasColumn($table, 'session_id'))     $t->unsignedBigInteger('session_id')->nullable();
            if (!Schema::hasColumn($table, 'sender_id'))      $t->unsignedBigInteger('sender_id')->nullable();
            if (!Schema::hasColumn($table, 'message'))        $t->text('message')->nullable();
            if (!Schema::hasColumn($table, 'sent_at'))        $t->timestamp('sent_at')->nullable();
        });

        // ── hd_sla_policies (more) ────────────────────────────────────────
        $this->patch('hd_sla_policies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_default'))  $t->boolean('is_default')->default(false);
        });

        // ── hd_escalation_rules ───────────────────────────────────────────
        $this->patch('hd_escalation_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sla_policy_id'))  $t->unsignedBigInteger('sla_policy_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))           $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'trigger_after'))  $t->integer('trigger_after')->default(60);
            if (!Schema::hasColumn($table, 'action'))         $t->string('action', 30)->default('notify');
        });

        // ── helpdesk_forum_replies ────────────────────────────────────────
        $this->patch('helpdesk_forum_replies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'content'))   $t->text('content')->nullable();
            if (!Schema::hasColumn($table, 'post_id'))   $t->unsignedBigInteger('post_id')->nullable();
            if (!Schema::hasColumn($table, 'author_id')) $t->unsignedBigInteger('author_id')->nullable();
        });
    }

    public function down(): void {}
};
