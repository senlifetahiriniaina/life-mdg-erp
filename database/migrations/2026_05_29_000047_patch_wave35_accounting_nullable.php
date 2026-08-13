<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) return;
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // Make NOT NULL columns nullable so factories can insert null values
        $this->patch('acc_journal_entries', function (Blueprint $t, $table) {
            if (Schema::hasColumn($table, 'entry_date')) {
                $t->date('entry_date')->nullable()->change();
            }
        });

        $this->patch('acc_cash_flow_forecasts', function (Blueprint $t, $table) {
            if (Schema::hasColumn($table, 'minimum_balance_threshold')) {
                $t->decimal('minimum_balance_threshold', 15, 4)->nullable()->change();
            }
        });

        $this->patch('acc_treasury_scenarios', function (Blueprint $t, $table) {
            if (Schema::hasColumn($table, 'scenario_balance')) {
                $t->decimal('scenario_balance', 15, 4)->nullable()->change();
            }
        });

        $this->patch('acc_audit_logs', function (Blueprint $t, $table) {
            if (Schema::hasColumn($table, 'event')) {
                $t->string('event')->nullable()->change();
            }
            if (!Schema::hasColumn($table, 'event')) {
                $t->string('event')->nullable();
            }
        });

        $this->patch('contract_usage_metrics', function (Blueprint $t, $table) {
            if (Schema::hasColumn($table, 'revenue_amount')) {
                $t->decimal('revenue_amount', 15, 2)->nullable()->change();
            }
        });

        $this->patch('acc_budgets', function (Blueprint $t, $table) {
            if (Schema::hasColumn($table, 'currency')) {
                $t->string('currency', 3)->nullable()->change();
            }
        });

        // acc_financial_reports: fiscal_period_start/end
        $this->patch('acc_financial_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'fiscal_period_start')) $t->date('fiscal_period_start')->nullable();
            if (!Schema::hasColumn($table, 'fiscal_period_end'))   $t->date('fiscal_period_end')->nullable();
        });

        $this->patch('acc_journal_entry_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'amount_currency')) $t->decimal('amount_currency', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'match_ref'))       $t->string('match_ref')->nullable();
        });

        $this->patch('acc_tax_calculation_audits', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'rules_applied'))       $t->json('rules_applied')->nullable();
            if (!Schema::hasColumn($table, 'calculation_steps'))   $t->json('calculation_steps')->nullable();
            if (!Schema::hasColumn($table, 'source_transaction_type')) $t->string('source_transaction_type')->nullable();
            if (!Schema::hasColumn($table, 'source_transaction_id'))   $t->unsignedBigInteger('source_transaction_id')->nullable();
            if (!Schema::hasColumn($table, 'jurisdiction'))        $t->string('jurisdiction')->nullable();
            if (!Schema::hasColumn($table, 'currency'))            $t->string('currency', 3)->nullable();
            if (!Schema::hasColumn($table, 'amount'))              $t->decimal('amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'calculated_tax'))      $t->decimal('calculated_tax', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'applied_deductions'))  $t->decimal('applied_deductions', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'net_tax'))             $t->decimal('net_tax', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'calculated_by_id'))    $t->unsignedBigInteger('calculated_by_id')->nullable();
            if (!Schema::hasColumn($table, 'calculated_at'))       $t->timestamp('calculated_at')->nullable();
            if (!Schema::hasColumn($table, 'verification_status')) $t->string('verification_status')->default('pending');
            if (!Schema::hasColumn($table, 'verified_by_id'))      $t->unsignedBigInteger('verified_by_id')->nullable();
            if (!Schema::hasColumn($table, 'verified_at'))          $t->timestamp('verified_at')->nullable();
            if (!Schema::hasColumn($table, 'modified_by_id'))       $t->unsignedBigInteger('modified_by_id')->nullable();
            if (!Schema::hasColumn($table, 'modified_at'))          $t->timestamp('modified_at')->nullable();
            if (!Schema::hasColumn($table, 'notes'))                $t->text('notes')->nullable();
        });
    }

    public function down(): void {}
};
