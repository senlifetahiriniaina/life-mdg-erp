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
        // ── hr_critical_positions (more) ──────────────────────────────────
        $this->patch('hr_critical_positions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_vacant'))  $t->boolean('is_vacant')->default(false);
        });

        // ── mfg_capacity_allocations ──────────────────────────────────────
        $this->patch('mfg_capacity_allocations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'production_order_id'))  $t->unsignedBigInteger('production_order_id')->nullable();
            if (!Schema::hasColumn($table, 'workcenter_id'))        $t->unsignedBigInteger('workcenter_id')->nullable();
            if (!Schema::hasColumn($table, 'planned_hours'))        $t->decimal('planned_hours', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'actual_hours'))         $t->decimal('actual_hours', 8, 2)->default(0);
            if (!Schema::hasColumn($table, 'start_date'))           $t->date('start_date')->nullable();
            if (!Schema::hasColumn($table, 'end_date'))             $t->date('end_date')->nullable();
        });

        // ── mfg_capacity_constraints (more) ───────────────────────────────
        $this->patch('mfg_capacity_constraints', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'used_hours'))  $t->decimal('used_hours', 8, 2)->default(0);
        });

        // ── pos_payment_terminals (more) ──────────────────────────────────
        $this->patch('pos_payment_terminals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'location'))  $t->string('location')->nullable();
        });

        // ── pos_cash_movements ────────────────────────────────────────────
        $this->patch('pos_cash_movements', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shift_id'))    $t->unsignedBigInteger('shift_id')->nullable();
            if (!Schema::hasColumn($table, 'amount'))      $t->decimal('amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'type'))        $t->string('type', 20)->default('in');
            if (!Schema::hasColumn($table, 'reason'))      $t->text('reason')->nullable();
            if (!Schema::hasColumn($table, 'cashier_id'))  $t->unsignedBigInteger('cashier_id')->nullable();
        });

        // ── ecommerce_rmas (more) ─────────────────────────────────────────
        $this->patch('ecommerce_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'received_at'))  $t->timestamp('received_at')->nullable();
        });

        // ── ecommerce_rfqs (more) ─────────────────────────────────────────
        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'subject'))  $t->string('subject')->nullable();
        });

        // ── ecommerce_vendors (more) ──────────────────────────────────────
        $this->patch('ecommerce_vendors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_sales'))  $t->decimal('total_sales', 15, 4)->default(0);
        });

        // ── wa_broadcast_campaigns (more) ─────────────────────────────────
        $this->patch('wa_broadcast_campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_recipients'))  $t->integer('total_recipients')->default(0);
        });

        // ── wa_chatbot_sessions (more) ────────────────────────────────────
        $this->patch('wa_chatbot_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'current_intent'))  $t->string('current_intent')->nullable();
        });

        // ── wa_chatbot_intents (more) ─────────────────────────────────────
        $this->patch('wa_chatbot_intents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'display_name'))  $t->string('display_name')->nullable();
        });

        // ── email_automation_flows (more) ─────────────────────────────────
        $this->patch('email_automation_flows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_type'))  $t->string('trigger_type', 30)->nullable();
        });

        // ── email_templates ───────────────────────────────────────────────
        $this->patch('email_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'subject'))     $t->string('subject')->nullable();
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'body'))        $t->text('body')->nullable();
            if (!Schema::hasColumn($table, 'type'))        $t->string('type', 20)->default('transactional');
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
        });

        // ── email_smtp_configs ────────────────────────────────────────────
        $this->patch('email_smtp_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'host'))      $t->string('host')->nullable();
            if (!Schema::hasColumn($table, 'port'))      $t->integer('port')->default(587);
            if (!Schema::hasColumn($table, 'username'))  $t->string('username')->nullable();
            if (!Schema::hasColumn($table, 'password'))  $t->text('password')->nullable();
            if (!Schema::hasColumn($table, 'encryption')) $t->string('encryption', 10)->default('tls');
            if (!Schema::hasColumn($table, 'from_email')) $t->string('from_email')->nullable();
            if (!Schema::hasColumn($table, 'from_name'))  $t->string('from_name')->nullable();
        });

        // ── email_segments ────────────────────────────────────────────────
        $this->patch('email_segments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))       $t->string('type', 20)->default('static');
            if (!Schema::hasColumn($table, 'name'))       $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'conditions')) $t->text('conditions')->nullable();
            if (!Schema::hasColumn($table, 'count'))      $t->integer('count')->default(0);
        });

        // ── email_campaigns (more) ────────────────────────────────────────
        $this->patch('email_campaigns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'from_email'))  $t->string('from_email')->nullable();
        });

        // ── email_journeys ────────────────────────────────────────────────
        $this->patch('email_journeys', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger'))    $t->string('trigger')->nullable();
            if (!Schema::hasColumn($table, 'name'))       $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))  $t->boolean('is_active')->default(true);
        });

        // ── email_domain_authentications (more) ───────────────────────────
        $this->patch('email_domain_authentications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'spf_record'))  $t->text('spf_record')->nullable();
        });

        // ── workflows (more) ──────────────────────────────────────────────
        $this->patch('workflows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_entity'))  $t->string('trigger_entity')->nullable();
        });

        // ── crm_sequence_steps (more) ─────────────────────────────────────
        $this->patch('crm_sequence_steps', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'body'))  $t->text('body')->nullable();
        });

        // ── core_custom_fields (more) ─────────────────────────────────────
        $this->patch('core_custom_fields', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'field_key'))  $t->string('field_key')->nullable();
        });

        // ── core_gdpr_consents (more) ─────────────────────────────────────
        $this->patch('core_gdpr_consents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'source'))  $t->string('source', 30)->nullable();
        });

        // ── core_approval_workflows (more) ────────────────────────────────
        $this->patch('core_approval_workflows', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'allow_parallel'))  $t->boolean('allow_parallel')->default(false);
        });

        // ── core_tenant_exchanges ─────────────────────────────────────────
        $this->patch('core_tenant_exchanges', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'source_tenant_id'))  $t->unsignedBigInteger('source_tenant_id')->nullable();
            if (!Schema::hasColumn($table, 'target_tenant_id'))  $t->unsignedBigInteger('target_tenant_id')->nullable();
            if (!Schema::hasColumn($table, 'data_type'))         $t->string('data_type', 30)->nullable();
            if (!Schema::hasColumn($table, 'payload'))           $t->text('payload')->nullable();
        });

        // ── core_workflow_states ──────────────────────────────────────────
        $this->patch('core_workflow_states', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workflow_definition_id'))  $t->unsignedBigInteger('workflow_definition_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))                    $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'is_initial'))              $t->boolean('is_initial')->default(false);
            if (!Schema::hasColumn($table, 'is_final'))                $t->boolean('is_final')->default(false);
        });

        // ── marketing_contacts (more) ─────────────────────────────────────
        $this->patch('marketing_contacts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'company'))  $t->string('company')->nullable();
        });
    }

    public function down(): void {}
};
