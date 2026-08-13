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
        // ── Planning ──────────────────────────────────────────────────────
        $this->patch('planning_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'min_employees'))     $t->integer('min_employees')->default(1);
            if (!Schema::hasColumn($table, 'max_employees'))     $t->integer('max_employees')->nullable();
            if (!Schema::hasColumn($table, 'break_duration'))    $t->integer('break_duration')->default(0);
            if (!Schema::hasColumn($table, 'color'))             $t->string('color', 10)->default('#3B82F6');
        });

        $this->patch('planning_schedule_conflicts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))       $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'severity'))          $t->string('severity', 20)->default('medium');
            if (!Schema::hasColumn($table, 'status'))            $t->string('status', 20)->default('unresolved');
            if (!Schema::hasColumn($table, 'resolved_by'))       $t->unsignedBigInteger('resolved_by')->nullable();
            if (!Schema::hasColumn($table, 'resolved_at'))       $t->timestamp('resolved_at')->nullable();
        });

        // ── Quality ───────────────────────────────────────────────────────
        $this->patch('quality_issues', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reported_at'))       $t->timestamp('reported_at')->nullable();
            if (!Schema::hasColumn($table, 'cost_of_quality'))   $t->decimal('cost_of_quality', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'root_cause'))        $t->text('root_cause')->nullable();
            if (!Schema::hasColumn($table, 'corrective_action')) $t->text('corrective_action')->nullable();
        });

        $this->patch('quality_inspections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity_rejected')) $t->decimal('quantity_rejected', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'defects_found'))     $t->integer('defects_found')->default(0);
            if (!Schema::hasColumn($table, 'completed_at'))      $t->timestamp('completed_at')->nullable();
        });

        // ── Documents ─────────────────────────────────────────────────────
        $this->patch('doc_catalog_generations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_id'))           $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))              $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'status'))            $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'config'))            $t->text('config')->nullable();
            if (!Schema::hasColumn($table, 'output_path'))       $t->string('output_path')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))      $t->timestamp('completed_at')->nullable();
        });

        $this->patch('document_workspaces', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'icon'))              $t->string('icon', 50)->nullable();
            if (!Schema::hasColumn($table, 'name'))              $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))       $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'owner_id'))          $t->unsignedBigInteger('owner_id')->nullable();
            if (!Schema::hasColumn($table, 'color'))             $t->string('color', 10)->nullable();
            if (!Schema::hasColumn($table, 'is_public'))         $t->boolean('is_public')->default(false);
        });

        $this->patch('doc_approval_workflows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by'))        $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('doc_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'category'))          $t->string('category', 50)->nullable();
            if (!Schema::hasColumn($table, 'created_by'))        $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'name'))              $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'content'))           $t->text('content')->nullable();
            if (!Schema::hasColumn($table, 'variables'))         $t->text('variables')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))         $t->boolean('is_active')->default(true);
        });

        // ── Email ─────────────────────────────────────────────────────────
        $this->patch('email_automation_flows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_config'))    $t->text('trigger_config')->nullable();
            if (!Schema::hasColumn($table, 'steps'))             $t->text('steps')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))        $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('email_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'html_content'))      $t->text('html_content')->nullable();
            if (!Schema::hasColumn($table, 'text_content'))      $t->text('text_content')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))        $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('email_smtp_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by'))        $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'name'))              $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'is_default'))        $t->boolean('is_default')->default(false);
        });

        $this->patch('email_segments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by'))        $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('email_domain_authentications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'dkim_selector'))     $t->string('dkim_selector')->nullable();
            if (!Schema::hasColumn($table, 'dmarc_record'))      $t->text('dmarc_record')->nullable();
            if (!Schema::hasColumn($table, 'mx_records'))        $t->text('mx_records')->nullable();
        });

        // ── Logistics ─────────────────────────────────────────────────────
        $this->patch('logistics_locations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'location_class'))    $t->string('location_class', 20)->default('standard');
            if (!Schema::hasColumn($table, 'max_weight'))        $t->decimal('max_weight', 10, 2)->nullable();
            if (!Schema::hasColumn($table, 'volume'))            $t->decimal('volume', 10, 2)->nullable();
            if (!Schema::hasColumn($table, 'temperature_zone'))  $t->string('temperature_zone', 20)->nullable();
        });

        $this->patch('logistics_customs_declarations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))              $t->string('type', 30)->default('import');
            if (!Schema::hasColumn($table, 'declared_value'))    $t->decimal('declared_value', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'currency'))          $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'incoterm'))          $t->string('incoterm', 10)->nullable();
            if (!Schema::hasColumn($table, 'status'))            $t->string('status', 20)->default('pending');
        });

        $this->patch('logistics_carrier_rates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'carrier_id'))        $t->unsignedBigInteger('carrier_id')->nullable();
            if (!Schema::hasColumn($table, 'zone'))              $t->string('zone', 50)->nullable();
            if (!Schema::hasColumn($table, 'weight_min'))        $t->decimal('weight_min', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'weight_max'))        $t->decimal('weight_max', 8, 2)->nullable();
            if (!Schema::hasColumn($table, 'rate'))              $t->decimal('rate', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'currency'))          $t->string('currency', 10)->default('XOF');
        });

        // ── MarketingAutomation ───────────────────────────────────────────
        $this->patch('contact_scores', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_id'))        $t->unsignedBigInteger('contact_id')->nullable();
            if (!Schema::hasColumn($table, 'score'))             $t->integer('score')->default(0);
            if (!Schema::hasColumn($table, 'factors'))           $t->text('factors')->nullable();
            if (!Schema::hasColumn($table, 'scored_at'))         $t->timestamp('scored_at')->nullable();
        });

        $this->patch('product_webhooks', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'url'))               $t->string('url')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))        $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'event'))             $t->string('event', 50)->nullable();
            if (!Schema::hasColumn($table, 'secret'))            $t->string('secret')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))         $t->boolean('is_active')->default(true);
        });

        // ── POS ───────────────────────────────────────────────────────────
        $this->patch('pos_tables', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'current_order_id'))  $t->unsignedBigInteger('current_order_id')->nullable();
            if (!Schema::hasColumn($table, 'status'))            $t->string('status', 20)->default('available');
            if (!Schema::hasColumn($table, 'last_occupied_at'))  $t->timestamp('last_occupied_at')->nullable();
        });

        $this->patch('pos_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'barcode_scanner'))   $t->string('barcode_scanner')->nullable();
            if (!Schema::hasColumn($table, 'display_tax'))       $t->boolean('display_tax')->default(true);
            if (!Schema::hasColumn($table, 'tip_enabled'))       $t->boolean('tip_enabled')->default(false);
            if (!Schema::hasColumn($table, 'offline_mode'))      $t->boolean('offline_mode')->default(true);
        });

        $this->patch('pos_cash_movements', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))       $t->text('description')->nullable();
        });

        $this->patch('pos_payment_terminals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'last_ping_at'))      $t->timestamp('last_ping_at')->nullable();
            if (!Schema::hasColumn($table, 'firmware_version'))  $t->string('firmware_version')->nullable();
        });

        $this->patch('pos_stores', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))              $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'address'))           $t->text('address')->nullable();
            if (!Schema::hasColumn($table, 'phone'))             $t->string('phone')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))         $t->boolean('is_active')->default(true);
        });

        $this->patch('pos_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'transaction_count')) $t->integer('transaction_count')->default(0);
            if (!Schema::hasColumn($table, 'terminal_id'))       $t->unsignedBigInteger('terminal_id')->nullable();
            if (!Schema::hasColumn($table, 'actual_cash'))       $t->decimal('actual_cash', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'discrepancy'))       $t->decimal('discrepancy', 15, 4)->default(0);
        });

        $this->patch('pos_loyalty_accounts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'customer_id'))       $t->unsignedBigInteger('customer_id')->nullable();
            if (!Schema::hasColumn($table, 'points'))            $t->integer('points')->default(0);
            if (!Schema::hasColumn($table, 'tier'))              $t->string('tier', 20)->default('bronze');
        });

        $this->patch('pos_location_stock', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'location_id'))       $t->unsignedBigInteger('location_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))        $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))          $t->decimal('quantity', 15, 4)->default(0);
        });

        // ── BI ────────────────────────────────────────────────────────────
        $this->patch('bi_kpi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'severity'))          $t->string('severity', 20)->default('warning');
            if (!Schema::hasColumn($table, 'resolved_at'))       $t->timestamp('resolved_at')->nullable();
        });

        $this->patch('bi_predictive_models', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'coefficients'))      $t->text('coefficients')->nullable();
            if (!Schema::hasColumn($table, 'features'))          $t->text('features')->nullable();
            if (!Schema::hasColumn($table, 'last_trained_at'))   $t->timestamp('last_trained_at')->nullable();
        });

        $this->patch('bi_anomalies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'expected_value'))    $t->decimal('expected_value', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'deviation_pct'))     $t->decimal('deviation_pct', 8, 4)->nullable();
        });

        $this->patch('bi_data_sources', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'connection_config')) $t->text('connection_config')->nullable();
            if (!Schema::hasColumn($table, 'last_synced_at'))    $t->timestamp('last_synced_at')->nullable();
        });

        $this->patch('bi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'metric_name'))       $t->string('metric_name')->nullable();
            if (!Schema::hasColumn($table, 'severity'))          $t->string('severity', 20)->default('warning');
        });

        $this->patch('bi_widgets', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'dashboard_id'))      $t->unsignedBigInteger('dashboard_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))              $t->string('type', 30)->default('chart');
            if (!Schema::hasColumn($table, 'config'))            $t->text('config')->nullable();
            if (!Schema::hasColumn($table, 'position'))          $t->text('position')->nullable();
        });

        $this->patch('bi_queries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sql_query'))         $t->text('sql_query')->nullable();
            if (!Schema::hasColumn($table, 'name'))              $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))        $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── Projects ──────────────────────────────────────────────────────
        $this->patch('prj_time_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'invoice_id'))        $t->unsignedBigInteger('invoice_id')->nullable();
        });

        $this->patch('prj_project_billing', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_billed'))      $t->decimal('total_billed', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'last_invoice_at'))   $t->timestamp('last_invoice_at')->nullable();
        });

        $this->patch('prj_resource_capacity', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_leave'))          $t->boolean('is_leave')->default(false);
            if (!Schema::hasColumn($table, 'leave_type'))        $t->string('leave_type', 30)->nullable();
        });

        $this->patch('project_task_dependencies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'lag_days'))          $t->integer('lag_days')->default(0);
        });

        $this->patch('prj_epics', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'color'))             $t->string('color', 10)->nullable();
            if (!Schema::hasColumn($table, 'status'))            $t->string('status', 20)->default('open');
        });

        $this->patch('prj_automation_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'conditions'))        $t->text('conditions')->nullable();
        });

        $this->patch('prj_team_members', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'can_edit_tasks'))    $t->boolean('can_edit_tasks')->default(true);
        });

        $this->patch('prj_tasks', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sprint_id'))         $t->unsignedBigInteger('sprint_id')->nullable();
            if (!Schema::hasColumn($table, 'story_points'))      $t->integer('story_points')->nullable();
            if (!Schema::hasColumn($table, 'blocked_by'))        $t->unsignedBigInteger('blocked_by')->nullable();
        });

        // ── Helpdesk ──────────────────────────────────────────────────────
        $this->patch('hd_kb_articles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reading_time_minutes')) $t->integer('reading_time_minutes')->default(1);
            if (!Schema::hasColumn($table, 'last_reviewed_at'))  $t->timestamp('last_reviewed_at')->nullable();
        });

        $this->patch('hd_sla_breaches', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'escalated'))         $t->boolean('escalated')->default(false);
            if (!Schema::hasColumn($table, 'escalated_at'))      $t->timestamp('escalated_at')->nullable();
        });

        $this->patch('hd_chat_messages', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))              $t->string('type', 20)->default('text');
            if (!Schema::hasColumn($table, 'attachments'))       $t->text('attachments')->nullable();
            if (!Schema::hasColumn($table, 'is_read'))           $t->boolean('is_read')->default(false);
        });

        $this->patch('hd_escalation_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_type'))      $t->string('trigger_type', 30)->default('time');
            if (!Schema::hasColumn($table, 'escalate_to_role'))  $t->string('escalate_to_role')->nullable();
        });

        $this->patch('helpdesk_forum_replies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'votes'))             $t->integer('votes')->default(0);
            if (!Schema::hasColumn($table, 'is_accepted'))       $t->boolean('is_accepted')->default(false);
        });

        $this->patch('hd_sla_policies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'response_time_hours')) $t->integer('response_time_hours')->default(4);
            if (!Schema::hasColumn($table, 'resolution_time_hours')) $t->integer('resolution_time_hours')->default(24);
        });
    }

    public function down(): void {}
};
