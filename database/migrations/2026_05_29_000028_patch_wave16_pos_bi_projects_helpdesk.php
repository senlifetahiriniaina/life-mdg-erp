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
        // ── POS ──────────────────────────────────────────────────────────

        $this->patch('pos_tables', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reserved_at'))     $t->timestamp('reserved_at')->nullable();
            if (!Schema::hasColumn($table, 'notes'))           $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'status'))          $t->string('status', 30)->default('available');
        });

        $this->patch('pos_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'floor_plan_enabled')) $t->boolean('floor_plan_enabled')->default(false);
            if (!Schema::hasColumn($table, 'name'))               $t->string('name')->nullable();
        });

        $this->patch('pos_location_stock', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'location_name'))  $t->string('location_name')->nullable();
            if (!Schema::hasColumn($table, 'location_id'))    $t->unsignedBigInteger('location_id')->nullable();
            if (!Schema::hasColumn($table, 'min_qty'))         $t->decimal('min_qty', 12, 4)->default(0);
        });

        $this->patch('pos_loyalty_accounts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'points_balance'))  $t->decimal('points_balance', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'total_earned'))    $t->decimal('total_earned', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'total_redeemed'))  $t->decimal('total_redeemed', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'tier'))            $t->string('tier', 50)->default('bronze');
            if (!Schema::hasColumn($table, 'customer_id'))     $t->unsignedBigInteger('customer_id')->nullable();
        });

        $this->patch('pos_loyalty_tiers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))            $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'min_points'))      $t->integer('min_points')->default(0);
            if (!Schema::hasColumn($table, 'discount_pct'))    $t->decimal('discount_pct', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'color'))           $t->string('color', 20)->nullable();
            if (!Schema::hasColumn($table, 'perks'))           $t->json('perks')->nullable();
        });

        $this->patch('pos_payment_terminals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'settings'))        $t->json('settings')->nullable();
            if (!Schema::hasColumn($table, 'location'))        $t->string('location')->nullable();
            if (!Schema::hasColumn($table, 'last_ping_at'))    $t->timestamp('last_ping_at')->nullable();
        });

        $this->patch('pos_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_cash_sales'))  $t->decimal('total_cash_sales', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'total_card_sales'))  $t->decimal('total_card_sales', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'cash_difference'))   $t->decimal('cash_difference', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'expected_cash'))     $t->decimal('expected_cash', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'closed_by'))         $t->unsignedBigInteger('closed_by')->nullable();
        });

        $this->patch('pos_stores', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'code'))     $t->string('code', 50)->nullable();
            if (!Schema::hasColumn($table, 'phone'))    $t->string('phone', 30)->nullable();
            if (!Schema::hasColumn($table, 'address'))  $t->string('address')->nullable();
        });

        // ── BI ───────────────────────────────────────────────────────────

        $this->patch('bi_queries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'datasource'))         $t->string('datasource')->default('default');
            if (!Schema::hasColumn($table, 'result_cache_ttl'))   $t->integer('result_cache_ttl')->default(0);
            if (!Schema::hasColumn($table, 'is_public'))          $t->boolean('is_public')->default(false);
            if (!Schema::hasColumn($table, 'created_by'))         $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('bi_widgets', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))             $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'dashboard_id'))      $t->unsignedBigInteger('dashboard_id')->nullable();
            if (!Schema::hasColumn($table, 'refresh_interval'))  $t->integer('refresh_interval')->default(300);
            if (!Schema::hasColumn($table, 'config'))            $t->json('config')->nullable();
        });

        $this->patch('bi_predictive_models', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'accuracy_score'))         $t->decimal('accuracy_score', 5, 4)->nullable();
            if (!Schema::hasColumn($table, 'coefficients'))           $t->json('coefficients')->nullable();
            if (!Schema::hasColumn($table, 'last_trained_at'))        $t->timestamp('last_trained_at')->nullable();
            if (!Schema::hasColumn($table, 'forecast_horizon_days'))  $t->integer('forecast_horizon_days')->default(30);
            if (!Schema::hasColumn($table, 'is_active'))              $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'model_type'))             $t->string('model_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'entity_type'))            $t->string('entity_type', 50)->nullable();
        });

        $this->patch('bi_anomalies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'actual_value'))       $t->decimal('actual_value', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'deviation_percent'))  $t->decimal('deviation_percent', 10, 4)->nullable();
            if (!Schema::hasColumn($table, 'entity_type'))        $t->string('entity_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'entity_id'))          $t->unsignedBigInteger('entity_id')->nullable();
            if (!Schema::hasColumn($table, 'metric_name'))        $t->string('metric_name')->nullable();
            if (!Schema::hasColumn($table, 'detected_at'))        $t->timestamp('detected_at')->nullable();
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('new');
        });

        $this->patch('bi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'check_interval_minutes'))  $t->integer('check_interval_minutes')->default(60);
            if (!Schema::hasColumn($table, 'metric_name'))             $t->string('metric_name')->nullable();
            if (!Schema::hasColumn($table, 'condition_type'))          $t->string('condition_type', 30)->nullable();
            if (!Schema::hasColumn($table, 'threshold'))               $t->decimal('threshold', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'channels'))                $t->json('channels')->nullable();
            if (!Schema::hasColumn($table, 'recipients'))              $t->json('recipients')->nullable();
        });

        $this->patch('bi_data_sources', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by'))          $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'connection_config'))   $t->text('connection_config')->nullable();
            if (!Schema::hasColumn($table, 'status'))              $t->string('status', 20)->default('inactive');
        });

        // ── Projects ─────────────────────────────────────────────────────

        $this->patch('prj_project_billing', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_hours'))   $t->decimal('total_hours', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'hourly_rate'))   $t->decimal('hourly_rate', 12, 4)->default(0);
            if (!Schema::hasColumn($table, 'budget_hours'))  $t->decimal('budget_hours', 12, 2)->nullable();
            if (!Schema::hasColumn($table, 'status'))        $t->string('status', 20)->default('active');
        });

        $this->patch('prj_resource_capacity', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes'))       $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'is_holiday'))  $t->boolean('is_holiday')->default(false);
            if (!Schema::hasColumn($table, 'is_leave'))    $t->boolean('is_leave')->default(false);
        });

        $this->patch('prj_team_members', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'can_manage_members'))  $t->boolean('can_manage_members')->default(false);
            if (!Schema::hasColumn($table, 'can_edit_tasks'))      $t->boolean('can_edit_tasks')->default(false);
            if (!Schema::hasColumn($table, 'left_at'))             $t->timestamp('left_at')->nullable();
            if (!Schema::hasColumn($table, 'joined_at'))           $t->timestamp('joined_at')->nullable();
        });

        $this->patch('prj_automation_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'actions'))     $t->json('actions')->nullable();
            if (!Schema::hasColumn($table, 'conditions'))  $t->json('conditions')->nullable();
            if (!Schema::hasColumn($table, 'active'))      $t->boolean('active')->default(true);
            if (!Schema::hasColumn($table, 'trigger'))     $t->string('trigger', 50)->nullable();
            if (!Schema::hasColumn($table, 'project_id'))  $t->unsignedBigInteger('project_id')->nullable();
        });

        // ── Helpdesk ─────────────────────────────────────────────────────

        // Make ticket_number nullable so factories work without it
        $this->patch('hd_tickets', function (Blueprint $t, $table) {
            if (Schema::hasColumn($table, 'ticket_number')) {
                // Already exists - can't easily change nullability without recreate
                // Instead add it via DB::statement workaround handled below
            } else {
                $t->string('ticket_number')->nullable();
            }
        });

        $this->patch('hd_sla_breaches', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes'))         $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'escalated'))     $t->boolean('escalated')->default(false);
            if (!Schema::hasColumn($table, 'escalated_at'))  $t->timestamp('escalated_at')->nullable();
        });

        $this->patch('hd_chat_messages', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'chat_session_id'))  $t->unsignedBigInteger('chat_session_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))             $t->string('type', 30)->default('text');
        });

        $this->patch('hd_escalation_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_hours'))   $t->integer('trigger_hours')->nullable();
            if (!Schema::hasColumn($table, 'trigger_type'))    $t->string('trigger_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'action_type'))     $t->string('action_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'action_config'))   $t->json('action_config')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))       $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'sla_policy_id'))   $t->unsignedBigInteger('sla_policy_id')->nullable();
        });

        $this->patch('helpdesk_csat_surveys', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'campaign_id'))  $t->unsignedBigInteger('campaign_id')->nullable();
            if (!Schema::hasColumn($table, 'ticket_id'))    $t->unsignedBigInteger('ticket_id')->nullable();
            if (!Schema::hasColumn($table, 'score'))        $t->integer('score')->nullable();
            if (!Schema::hasColumn($table, 'comment'))      $t->text('comment')->nullable();
            if (!Schema::hasColumn($table, 'status'))       $t->string('status', 20)->default('pending');
        });
    }

    public function down(): void {}
};
