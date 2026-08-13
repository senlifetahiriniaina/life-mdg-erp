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

        $this->patch('pos_registers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'opening_float'))  $t->decimal('opening_float', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'current_float'))  $t->decimal('current_float', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'opened_by'))      $t->unsignedBigInteger('opened_by')->nullable();
            if (!Schema::hasColumn($table, 'closed_by'))      $t->unsignedBigInteger('closed_by')->nullable();
            if (!Schema::hasColumn($table, 'opened_at'))      $t->timestamp('opened_at')->nullable();
            if (!Schema::hasColumn($table, 'closed_at'))      $t->timestamp('closed_at')->nullable();
        });

        $this->patch('pos_loyalty_transactions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'loyalty_account_id'))  $t->unsignedBigInteger('loyalty_account_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))                $t->string('type', 20)->nullable();
            if (!Schema::hasColumn($table, 'points'))              $t->decimal('points', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'balance_after'))       $t->decimal('balance_after', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'note'))                $t->string('note')->nullable();
        });

        $this->patch('pos_loyalty_rewards', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'points_cost'))  $t->integer('points_cost')->default(0);
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 20)->nullable();
            if (!Schema::hasColumn($table, 'value'))        $t->decimal('value', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'active'))       $t->boolean('active')->default(true);
        });

        $this->patch('pos_location_stock', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'qty'))         $t->decimal('qty', 12, 4)->default(0);
            if (!Schema::hasColumn($table, 'product_id'))  $t->unsignedBigInteger('product_id')->nullable();
        });

        // ── POS sessions ────────────────────────────────────────────────

        $this->patch('pos_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cashier_id'))        $t->unsignedBigInteger('cashier_id')->nullable();
            if (!Schema::hasColumn($table, 'opening_balance'))   $t->decimal('opening_balance', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'expected_balance'))  $t->decimal('expected_balance', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'opened_at'))         $t->timestamp('opened_at')->nullable();
        });

        // ── Helpdesk additional ──────────────────────────────────────────

        $this->patch('helpdesk_csat_surveys', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sent_at'))       $t->timestamp('sent_at')->nullable();
            if (!Schema::hasColumn($table, 'responded_at'))  $t->timestamp('responded_at')->nullable();
            if (!Schema::hasColumn($table, 'agent_id'))      $t->unsignedBigInteger('agent_id')->nullable();
        });

        $this->patch('hd_tickets', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'source_ref'))  $t->string('source_ref')->nullable();
            if (!Schema::hasColumn($table, 'sla_id'))      $t->unsignedBigInteger('sla_id')->nullable();
        });

        $this->patch('hd_sla_policies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'business_hours'))  $t->json('business_hours')->nullable();
        });

        // ── BI ───────────────────────────────────────────────────────────

        $this->patch('bi_kpi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_count'))       $t->integer('trigger_count')->default(0);
            if (!Schema::hasColumn($table, 'last_triggered_at'))   $t->timestamp('last_triggered_at')->nullable();
        });

        $this->patch('bi_anomalies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'acknowledged_at'))  $t->timestamp('acknowledged_at')->nullable();
            if (!Schema::hasColumn($table, 'acknowledged_by'))  $t->unsignedBigInteger('acknowledged_by')->nullable();
        });

        $this->patch('bi_alert_events', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'acknowledged_at'))  $t->timestamp('acknowledged_at')->nullable();
            if (!Schema::hasColumn($table, 'acknowledged_by'))  $t->unsignedBigInteger('acknowledged_by')->nullable();
        });

        $this->patch('bi_scheduled_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'last_sent_at'))  $t->timestamp('last_sent_at')->nullable();
            if (!Schema::hasColumn($table, 'next_send_at'))  $t->timestamp('next_send_at')->nullable();
        });

        $this->patch('bi_queries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'last_run_at'))  $t->timestamp('last_run_at')->nullable();
        });

        $this->patch('bi_data_sources', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'last_tested_at'))  $t->timestamp('last_tested_at')->nullable();
        });

        $this->patch('bi_alert_events', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'alert_id'))          $t->unsignedBigInteger('alert_id')->nullable();
            if (!Schema::hasColumn($table, 'triggered_value'))   $t->decimal('triggered_value', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'threshold'))         $t->decimal('threshold', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'message'))           $t->text('message')->nullable();
            if (!Schema::hasColumn($table, 'severity'))          $t->string('severity', 20)->nullable();
            if (!Schema::hasColumn($table, 'acknowledged'))      $t->boolean('acknowledged')->default(false);
        });
    }

    public function down(): void {}
};
