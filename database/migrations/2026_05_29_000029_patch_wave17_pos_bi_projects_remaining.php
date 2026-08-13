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
        // ── POS additional tables ────────────────────────────────────────

        $this->patch('pos_terminal_transactions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'terminal_id'))   $t->unsignedBigInteger('terminal_id')->nullable();
            if (!Schema::hasColumn($table, 'session_id'))    $t->unsignedBigInteger('session_id')->nullable();
            if (!Schema::hasColumn($table, 'amount'))        $t->decimal('amount', 12, 2)->default(0);
            if (!Schema::hasColumn($table, 'currency'))      $t->string('currency', 3)->default('XOF');
            if (!Schema::hasColumn($table, 'status'))        $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'nfc_token'))     $t->string('nfc_token')->nullable();
            if (!Schema::hasColumn($table, 'card_last4'))    $t->string('card_last4', 4)->nullable();
            if (!Schema::hasColumn($table, 'card_brand'))    $t->string('card_brand', 20)->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))   $t->timestamp('approved_at')->nullable();
        });

        $this->patch('pos_table_reservations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'table_id'))         $t->unsignedBigInteger('table_id')->nullable();
            if (!Schema::hasColumn($table, 'customer_name'))    $t->string('customer_name')->nullable();
            if (!Schema::hasColumn($table, 'customer_phone'))   $t->string('customer_phone')->nullable();
            if (!Schema::hasColumn($table, 'party_size'))       $t->integer('party_size')->default(1);
            if (!Schema::hasColumn($table, 'reserved_for'))     $t->timestamp('reserved_for')->nullable();
            if (!Schema::hasColumn($table, 'status'))           $t->string('status', 20)->default('pending');
            if (!Schema::hasColumn($table, 'notes'))            $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))       $t->unsignedBigInteger('created_by')->nullable();
        });

        $this->patch('pos_registers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'store_id'))     $t->unsignedBigInteger('store_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'identifier'))   $t->string('identifier')->nullable();
            if (!Schema::hasColumn($table, 'status'))       $t->string('status', 20)->default('active');
        });

        $this->patch('pos_cash_movements', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by'))   $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'description'))  $t->string('description')->nullable();
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 30)->nullable();
            if (!Schema::hasColumn($table, 'amount'))       $t->decimal('amount', 12, 2)->default(0);
        });

        // ── BI additional columns ────────────────────────────────────────

        $this->patch('bi_kpi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notification_channels'))  $t->json('notification_channels')->nullable();
            if (!Schema::hasColumn($table, 'recipients'))             $t->json('recipients')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))             $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'condition'))              $t->string('condition', 20)->nullable();
        });

        $this->patch('bi_alerts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))  $t->string('status', 20)->default('active');
            if (!Schema::hasColumn($table, 'name'))    $t->string('name')->nullable();
        });

        $this->patch('bi_forecasts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'predictive_model_id'))  $t->unsignedBigInteger('predictive_model_id')->nullable();
            if (!Schema::hasColumn($table, 'forecast_date'))        $t->date('forecast_date')->nullable();
            if (!Schema::hasColumn($table, 'forecast_value'))       $t->decimal('forecast_value', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'lower_bound'))          $t->decimal('lower_bound', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'upper_bound'))          $t->decimal('upper_bound', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'actual_value'))         $t->decimal('actual_value', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'error_percent'))        $t->decimal('error_percent', 10, 4)->nullable();
        });

        // ── Projects additional columns ──────────────────────────────────

        $this->patch('prj_tasks', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'epic_id'))  $t->unsignedBigInteger('epic_id')->nullable();
        });
    }

    public function down(): void {}
};
