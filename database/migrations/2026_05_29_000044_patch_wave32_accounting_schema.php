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
        // acc_tax_deductions: missing tax_rule_id, name, type, amount, percentage, max_amount, conditions, jurisdiction, is_active
        $this->patch('acc_tax_deductions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'tax_rule_id')) {
                $t->unsignedBigInteger('tax_rule_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'name')) {
                $t->string('name')->nullable();
            }
            if (!Schema::hasColumn($table, 'type')) {
                $t->string('type')->default('standard');
            }
            if (!Schema::hasColumn($table, 'amount')) {
                $t->decimal('amount', 15, 2)->nullable();
            }
            if (!Schema::hasColumn($table, 'percentage')) {
                $t->decimal('percentage', 5, 2)->nullable();
            }
            if (!Schema::hasColumn($table, 'max_amount')) {
                $t->decimal('max_amount', 15, 2)->nullable();
            }
            if (!Schema::hasColumn($table, 'conditions')) {
                $t->json('conditions')->nullable();
            }
            if (!Schema::hasColumn($table, 'jurisdiction')) {
                $t->string('jurisdiction')->nullable();
            }
            if (!Schema::hasColumn($table, 'applies_to')) {
                $t->string('applies_to')->default('both')->nullable();
            }
            if (!Schema::hasColumn($table, 'effective_date')) {
                $t->date('effective_date')->nullable();
            }
            if (!Schema::hasColumn($table, 'expiry_date')) {
                $t->date('expiry_date')->nullable();
            }
            if (!Schema::hasColumn($table, 'currency')) {
                $t->string('currency', 3)->nullable();
            }
            if (!Schema::hasColumn($table, 'is_active')) {
                $t->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn($table, 'deleted_at')) {
                $t->softDeletes();
            }
        });

        // acc_tax_compliance: missing due_date
        // (already has tax_type, jurisdiction, requirement_type — due_date is a base column)

        // acc_consolidation_entities: missing consolidation_method, entity_type, ownership_percentage, exchange_rate
        $this->patch('acc_consolidation_entities', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'entity_type')) {
                $t->string('entity_type')->default('subsidiary');
            }
            if (!Schema::hasColumn($table, 'consolidation_method')) {
                $t->string('consolidation_method')->default('full');
            }
            if (!Schema::hasColumn($table, 'ownership_percentage')) {
                $t->decimal('ownership_percentage', 5, 2)->default(100);
            }
            if (!Schema::hasColumn($table, 'exchange_rate')) {
                $t->decimal('exchange_rate', 15, 6)->default(1);
            }
            if (!Schema::hasColumn($table, 'currency')) {
                $t->string('currency', 3)->default('USD');
            }
            if (!Schema::hasColumn($table, 'is_eliminating_entity')) {
                $t->boolean('is_eliminating_entity')->default(false);
            }
            if (!Schema::hasColumn($table, 'order')) {
                $t->integer('order')->default(0);
            }
            if (!Schema::hasColumn($table, 'company_id')) {
                $t->unsignedBigInteger('company_id')->nullable();
            }
        });

        // acc_journal_entries: missing exchange_rate, currency
        $this->patch('acc_journal_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'currency')) {
                $t->string('currency', 3)->default('USD');
            }
            if (!Schema::hasColumn($table, 'exchange_rate')) {
                $t->decimal('exchange_rate', 15, 6)->default(1);
            }
        });

        // acc_reconciliations: missing bank_statement_balance, difference_amount, statement_balance, reconciled_balance
        $this->patch('acc_reconciliations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'bank_statement_balance')) {
                $t->decimal('bank_statement_balance', 15, 4)->nullable();
            }
            if (!Schema::hasColumn($table, 'difference_amount')) {
                $t->decimal('difference_amount', 15, 4)->nullable();
            }
            if (!Schema::hasColumn($table, 'statement_balance')) {
                $t->decimal('statement_balance', 15, 4)->nullable();
            }
            if (!Schema::hasColumn($table, 'reconciled_balance')) {
                $t->decimal('reconciled_balance', 15, 4)->nullable();
            }
        });

        // acc_tax_compliance: missing tax_type, jurisdiction, requirement_type
        $this->patch('acc_tax_compliance', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'jurisdiction')) {
                $t->string('jurisdiction', 10)->nullable();
            }
            if (!Schema::hasColumn($table, 'tax_type')) {
                $t->string('tax_type')->nullable();
            }
            if (!Schema::hasColumn($table, 'requirement_type')) {
                $t->string('requirement_type')->nullable();
            }
            if (!Schema::hasColumn($table, 'due_date')) {
                $t->date('due_date')->nullable();
            }
            if (!Schema::hasColumn($table, 'filing_date')) {
                $t->date('filing_date')->nullable();
            }
            if (!Schema::hasColumn($table, 'deadline_days_before')) {
                $t->integer('deadline_days_before')->default(0);
            }
            if (!Schema::hasColumn($table, 'status')) {
                $t->string('status')->default('pending');
            }
            if (!Schema::hasColumn($table, 'reference_number')) {
                $t->string('reference_number')->nullable();
            }
            if (!Schema::hasColumn($table, 'filed_by_id')) {
                $t->unsignedBigInteger('filed_by_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'submitted_at')) {
                $t->timestamp('submitted_at')->nullable();
            }
            if (!Schema::hasColumn($table, 'verified_by_id')) {
                $t->unsignedBigInteger('verified_by_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'verified_at')) {
                $t->timestamp('verified_at')->nullable();
            }
            if (!Schema::hasColumn($table, 'notes')) {
                $t->text('notes')->nullable();
            }
        });

        // acc_tax_categories: missing name, deleted_at (SoftDeletes)
        $this->patch('acc_tax_categories', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name')) {
                $t->string('name')->nullable();
            }
            if (!Schema::hasColumn($table, 'deleted_at')) {
                $t->softDeletes();
            }
            try { $t->unique('code'); } catch (\Throwable $e) { /* already exists */ }
        });

        // acc_tax_rules: missing tax_category_id, code, name, rule_type, priority, jurisdiction, calculation_logic, conditions, is_active, currency
        $this->patch('acc_tax_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'tax_category_id')) {
                $t->unsignedBigInteger('tax_category_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'code')) {
                $t->string('code')->nullable();
            }
            if (!Schema::hasColumn($table, 'name')) {
                $t->string('name')->nullable();
            }
            if (!Schema::hasColumn($table, 'rule_type')) {
                $t->string('rule_type')->default('percentage');
            }
            if (!Schema::hasColumn($table, 'priority')) {
                $t->integer('priority')->default(1);
            }
            if (!Schema::hasColumn($table, 'jurisdiction')) {
                $t->string('jurisdiction')->nullable();
            }
            if (!Schema::hasColumn($table, 'currency')) {
                $t->string('currency', 3)->nullable();
            }
            if (!Schema::hasColumn($table, 'calculation_logic')) {
                $t->json('calculation_logic')->nullable();
            }
            if (!Schema::hasColumn($table, 'conditions')) {
                $t->json('conditions')->nullable();
            }
            if (!Schema::hasColumn($table, 'is_active')) {
                $t->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn($table, 'effective_date')) {
                $t->date('effective_date')->nullable();
            }
            if (!Schema::hasColumn($table, 'expiry_date')) {
                $t->date('expiry_date')->nullable();
            }
            if (!Schema::hasColumn($table, 'deleted_at')) {
                $t->softDeletes();
            }
        });

        // acc_bank_transactions: missing is_ignored
        $this->patch('acc_bank_transactions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_ignored')) {
                $col = $t->boolean('is_ignored')->default(false);
                if (Schema::hasColumn($table, 'reconciled')) { $col; }
            }
        });

        // acc_budgets: missing total_spent, parent_budget_id, fiscal_year_start, fiscal_year_end, department, currency
        $this->patch('acc_budgets', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_spent')) {
                $t->decimal('total_spent', 15, 2)->default(0);
            }
            if (!Schema::hasColumn($table, 'parent_budget_id')) {
                $t->unsignedBigInteger('parent_budget_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'fiscal_year_start')) {
                $t->date('fiscal_year_start')->nullable();
            }
            if (!Schema::hasColumn($table, 'fiscal_year_end')) {
                $t->date('fiscal_year_end')->nullable();
            }
            if (!Schema::hasColumn($table, 'department')) {
                $t->string('department')->nullable();
            }
            if (!Schema::hasColumn($table, 'currency')) {
                $t->string('currency', 3)->default('XOF');
            }
        });

        // acc_budget_lines: missing account_id, period_month, period_year, budgeted_amount, actual_amount,
        //                   variance, category, description, spent_amount, period, month, quarter, notes
        $this->patch('acc_budget_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'account_id')) {
                $t->unsignedBigInteger('account_id')->nullable();
            }
            if (!Schema::hasColumn($table, 'period_month')) {
                $t->unsignedTinyInteger('period_month')->nullable();
            }
            if (!Schema::hasColumn($table, 'period_year')) {
                $t->unsignedSmallInteger('period_year')->nullable();
            }
            if (!Schema::hasColumn($table, 'budgeted_amount')) {
                $t->decimal('budgeted_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn($table, 'actual_amount')) {
                $t->decimal('actual_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn($table, 'variance')) {
                $t->decimal('variance', 15, 2)->default(0);
            }
            if (!Schema::hasColumn($table, 'spent_amount')) {
                $t->decimal('spent_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn($table, 'category')) {
                $t->string('category')->nullable();
            }
            if (!Schema::hasColumn($table, 'description')) {
                $t->text('description')->nullable();
            }
            if (!Schema::hasColumn($table, 'notes')) {
                $t->text('notes')->nullable();
            }
            if (!Schema::hasColumn($table, 'period')) {
                $t->string('period')->default('annual');
            }
            if (!Schema::hasColumn($table, 'month')) {
                $t->unsignedTinyInteger('month')->nullable();
            }
            if (!Schema::hasColumn($table, 'quarter')) {
                $t->unsignedTinyInteger('quarter')->nullable();
            }
        });
    }

    public function down(): void {}
};
