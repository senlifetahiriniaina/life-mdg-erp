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
        // acc_bank_transactions: missing bank_account_id
        $this->patch('acc_bank_transactions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'bank_account_id')) {
                $t->unsignedBigInteger('bank_account_id')->nullable();
            }
        });

        // acc_consolidations: missing name, status, period_start, period_end, company_id, created_by
        $this->patch('acc_consolidations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name')) {
                $t->string('name')->nullable();
            }
            if (!Schema::hasColumn($table, 'status')) {
                $t->string('status')->default('draft');
            }
            if (!Schema::hasColumn($table, 'period_start')) {
                $t->date('period_start')->nullable();
            }
            if (!Schema::hasColumn($table, 'period_end')) {
                $t->date('period_end')->nullable();
            }
            if (!Schema::hasColumn($table, 'company_id')) {
                $t->unsignedBigInteger('company_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'created_by')) {
                $t->unsignedBigInteger('created_by')->nullable();
            }
        });

        // acc_financial_reports: missing name, type, period_start, period_end, status, data, company_id, created_by
        $this->patch('acc_financial_reports', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name')) {
                $t->string('name')->nullable();
            }
            if (!Schema::hasColumn($table, 'type')) {
                $t->string('type')->default('balance_sheet');
            }
            if (!Schema::hasColumn($table, 'period_start')) {
                $t->date('period_start')->nullable();
            }
            if (!Schema::hasColumn($table, 'period_end')) {
                $t->date('period_end')->nullable();
            }
            if (!Schema::hasColumn($table, 'status')) {
                $t->string('status')->default('draft');
            }
            if (!Schema::hasColumn($table, 'data')) {
                $t->json('data')->nullable();
            }
            if (!Schema::hasColumn($table, 'company_id')) {
                $t->unsignedBigInteger('company_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'created_by')) {
                $t->unsignedBigInteger('created_by')->nullable();
            }
        });

        // acc_gl_accounts: missing company_id
        $this->patch('acc_gl_accounts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'company_id')) {
                $t->unsignedBigInteger('company_id')->nullable();
            }
        });

        // acc_gl_entries: missing journal_id (alias for gl_journal_id)
        $this->patch('acc_gl_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'journal_id')) {
                $t->unsignedBigInteger('journal_id')->nullable();
            }
        });

        // acc_gl_journals: missing status
        $this->patch('acc_gl_journals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status')) {
                $t->string('status')->default('active');
            }
        });

        // acc_vat_rates: missing is_active
        $this->patch('acc_vat_rates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_active')) {
                $t->boolean('is_active')->default(true);
            }
        });

        // acc_tax_rule_audit_logs: ensure all columns exist
        $this->patch('acc_tax_rule_audit_logs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'tax_rule_id')) {
                $t->unsignedBigInteger('tax_rule_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'action')) {
                $t->string('action')->default('created');
            }
            if (!Schema::hasColumn($table, 'original_data')) {
                $t->json('original_data')->nullable();
            }
            if (!Schema::hasColumn($table, 'changed_data')) {
                $t->json('changed_data')->nullable();
            }
            if (!Schema::hasColumn($table, 'jurisdiction')) {
                $t->string('jurisdiction')->nullable();
            }
            if (!Schema::hasColumn($table, 'tax_category_id')) {
                $t->unsignedBigInteger('tax_category_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'changed_by_id')) {
                $t->unsignedBigInteger('changed_by_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'ip_address')) {
                $t->string('ip_address')->nullable();
            }
            if (!Schema::hasColumn($table, 'user_agent')) {
                $t->text('user_agent')->nullable();
            }
            if (!Schema::hasColumn($table, 'changed_at')) {
                $t->timestamp('changed_at')->nullable();
            }
        });
    }

    public function down(): void {}
};
