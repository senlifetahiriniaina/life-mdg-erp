<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates all missing acc_* accounting tables.
 *
 * Each block is guarded by Schema::hasTable() to make this migration
 * fully idempotent — safe to run even if some tables already exist.
 *
 * Design constraints:
 *  - SQLite-compatible: no JSON columns (use text), no enum (use string)
 *  - Monetary amounts: decimal(15,4)
 *  - All foreign keys are nullable() to avoid constraint errors
 *  - softDeletes() on primary business entities
 */
return new class extends Migration
{
    // -------------------------------------------------------------------------
    // UP
    // -------------------------------------------------------------------------
    public function up(): void
    {
        // ── 1. acc_journals ────────────────────────────────────────────────
        if (! Schema::hasTable('acc_journals')) {
            Schema::create('acc_journals', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('type')->default('general'); // general, sales, purchase, bank, cash
                $table->string('currency', 10)->default('XOF');
                $table->unsignedBigInteger('default_account_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 2. acc_gl_accounts ─────────────────────────────────────────────
        if (! Schema::hasTable('acc_gl_accounts')) {
            Schema::create('acc_gl_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('account_number')->unique();
                $table->string('account_name');
                $table->string('account_type');   // asset, liability, equity, revenue, expense
                $table->string('normal_balance')->default('debit'); // debit | credit
                $table->text('description')->nullable();
                $table->decimal('balance', 15, 4)->default(0);
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 3. acc_chart_of_accounts ───────────────────────────────────────
        if (! Schema::hasTable('acc_chart_of_accounts')) {
            Schema::create('acc_chart_of_accounts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('type');   // asset, liability, equity, revenue, expense
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('parent_id')
                    ->references('id')
                    ->on('acc_chart_of_accounts')
                    ->onDelete('set null');
            });
        }

        // ── 4. acc_invoices ────────────────────────────────────────────────
        if (! Schema::hasTable('acc_invoices')) {
            Schema::create('acc_invoices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('journal_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('number')->unique();
                $table->string('type')->default('invoice'); // invoice | bill | credit_note
                $table->unsignedBigInteger('partner_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('partner_name')->nullable();
                $table->string('partner_type')->nullable(); // customer | supplier
                $table->date('invoice_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status')->default('draft'); // draft | posted | paid | cancelled
                $table->string('currency', 10)->default('XOF');
                $table->decimal('exchange_rate', 15, 6)->default(1);
                $table->decimal('subtotal', 15, 4)->default(0);
                $table->decimal('tax_amount', 15, 4)->default(0);
                $table->decimal('total', 15, 4)->default(0);
                $table->decimal('amount_paid', 15, 4)->default(0);
                $table->decimal('paid_amount', 15, 4)->default(0);
                $table->decimal('amount_due', 15, 4)->default(0);
                $table->text('notes')->nullable();
                $table->string('payment_terms')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 5. acc_invoice_lines ───────────────────────────────────────────
        if (! Schema::hasTable('acc_invoice_lines')) {
            Schema::create('acc_invoice_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->text('description')->nullable();
                $table->decimal('quantity', 15, 4)->default(1);
                $table->decimal('unit_price', 15, 4)->default(0);
                $table->decimal('tax_rate', 15, 2)->default(0);
                $table->decimal('subtotal', 15, 4)->default(0);
                $table->decimal('tax_amount', 15, 4)->default(0);
                $table->decimal('total', 15, 4)->default(0);
                $table->string('match_ref')->nullable();
                $table->unsignedBigInteger('matched_by')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->timestamps();
            });
        }

        // ── 6. acc_journal_entries ─────────────────────────────────────────
        if (! Schema::hasTable('acc_journal_entries')) {
            Schema::create('acc_journal_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('journal_id')->nullable();
                $table->string('entry_number')->nullable()->unique();
                $table->date('entry_date');
                $table->unsignedBigInteger('gl_account_id')->nullable();
                $table->string('entry_type')->default('debit'); // debit | credit
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('draft'); // draft | posted | cancelled
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 7. acc_journal_entry_lines ─────────────────────────────────────
        if (! Schema::hasTable('acc_journal_entry_lines')) {
            Schema::create('acc_journal_entry_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('entry_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->text('description')->nullable();
                $table->decimal('debit', 15, 4)->default(0);
                $table->decimal('credit', 15, 4)->default(0);
                $table->string('currency', 10)->nullable();
                $table->decimal('currency_amount', 15, 4)->nullable();
                $table->timestamps();
            });
        }

        // ── 8. acc_expenses ────────────────────────────────────────────────
        if (! Schema::hasTable('acc_expenses')) {
            Schema::create('acc_expenses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('expense_number')->nullable()->unique();
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('expense_category_id')->nullable();
                $table->date('expense_date')->nullable();
                $table->text('description')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->decimal('amount_approved', 15, 4)->nullable();
                $table->decimal('amount_reimbursed', 15, 4)->nullable();
                $table->decimal('tax_amount', 15, 4)->nullable();
                $table->unsignedBigInteger('tax_category_id')->nullable();
                $table->string('status')->default('draft'); // draft | submitted | approved | rejected | paid
                $table->string('priority')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('submitted_by_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejected_reason')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('gl_account_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 9. acc_expense_lines ───────────────────────────────────────────
        if (! Schema::hasTable('acc_expense_lines')) {
            Schema::create('acc_expense_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('report_id')->nullable();
                $table->date('date')->nullable();
                $table->string('category')->nullable();
                $table->text('description')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->string('receipt_url')->nullable();
                $table->decimal('km', 15, 2)->nullable();
                $table->timestamps();
            });
        }

        // ── 10. acc_expense_reports ────────────────────────────────────────
        if (! Schema::hasTable('acc_expense_reports')) {
            Schema::create('acc_expense_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('title');
                $table->string('status')->default('draft');
                $table->decimal('total', 15, 4)->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 11. acc_budgets ────────────────────────────────────────────────
        if (! Schema::hasTable('acc_budgets')) {
            Schema::create('acc_budgets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('budget_period_start')->nullable();
                $table->date('budget_period_end')->nullable();
                $table->string('fiscal_year')->nullable();
                $table->decimal('total_budget', 15, 4)->default(0);
                $table->decimal('budgeted_amount', 15, 4)->default(0);
                $table->decimal('actual_amount', 15, 4)->default(0);
                $table->string('status')->default('draft'); // draft | active | approved | closed
                $table->unsignedBigInteger('approved_by_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('gl_account_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 12. acc_budget_lines ───────────────────────────────────────────
        if (! Schema::hasTable('acc_budget_lines')) {
            Schema::create('acc_budget_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('budget_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->integer('period_month')->nullable();
                $table->integer('period_year')->nullable();
                $table->decimal('budgeted_amount', 15, 4)->default(0);
                $table->decimal('actual_amount', 15, 4)->default(0);
                $table->decimal('variance', 15, 4)->default(0);
                $table->text('notes')->nullable();
                $table->string('category')->nullable();
                $table->text('description')->nullable();
                $table->decimal('spent_amount', 15, 4)->default(0);
                $table->string('period')->nullable();
                $table->integer('month')->nullable();
                $table->integer('quarter')->nullable();
                $table->timestamps();
            });
        }

        // ── 13. acc_budget_scenarios ───────────────────────────────────────
        if (! Schema::hasTable('acc_budget_scenarios')) {
            Schema::create('acc_budget_scenarios', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('base_budget_id')->nullable();
                $table->string('scenario_type')->nullable(); // optimistic | base | pessimistic
                $table->string('adjustment_type')->nullable(); // percentage | absolute
                $table->decimal('revenue_adjustment', 15, 4)->default(0);
                $table->decimal('expense_adjustment', 15, 4)->default(0);
                $table->text('description')->nullable();
                $table->text('assumptions')->nullable(); // JSON stored as text
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 14. acc_bank_accounts ──────────────────────────────────────────
        if (! Schema::hasTable('acc_bank_accounts')) {
            Schema::create('acc_bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('bank_name');
                $table->string('account_number')->nullable();
                $table->string('currency', 10)->default('XOF');
                $table->decimal('current_balance', 15, 4)->default(0);
                $table->timestamp('last_reconciled_at')->nullable();
                $table->decimal('last_reconciled_balance', 15, 4)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 15. acc_bank_statements ────────────────────────────────────────
        if (! Schema::hasTable('acc_bank_statements')) {
            Schema::create('acc_bank_statements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->date('statement_date');
                $table->decimal('opening_balance', 15, 4)->default(0);
                $table->decimal('closing_balance', 15, 4)->default(0);
                $table->string('status')->default('pending'); // pending | reconciled
                $table->integer('transaction_count')->default(0);
                $table->integer('matched_count')->default(0);
                $table->timestamp('reconciled_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 16. acc_bank_transactions ──────────────────────────────────────
        if (! Schema::hasTable('acc_bank_transactions')) {
            Schema::create('acc_bank_transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('statement_id')->nullable();
                $table->date('transaction_date');
                $table->text('description')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('reference')->nullable();
                $table->string('status')->default('pending'); // pending | matched | ignored
                $table->unsignedBigInteger('matched_entry_id')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->timestamps();
            });
        }

        // ── 17. acc_open_banking_connections ──────────────────────────────
        if (! Schema::hasTable('acc_open_banking_connections')) {
            Schema::create('acc_open_banking_connections', function (Blueprint $table): void {
                $table->id();
                $table->string('bank_name');
                $table->string('bank_code')->nullable();
                $table->string('status')->default('active');
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->text('external_account_ids')->nullable(); // JSON as text
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 18. acc_bank_feeds ─────────────────────────────────────────────
        if (! Schema::hasTable('acc_bank_feeds')) {
            Schema::create('acc_bank_feeds', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('connection_id')->nullable();
                $table->string('external_account_id')->nullable();
                $table->string('account_name')->nullable();
                $table->string('iban')->nullable();
                $table->string('currency', 10)->default('XOF');
                $table->decimal('balance', 15, 4)->default(0);
                $table->date('last_transaction_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 19. acc_bank_feed_transactions ────────────────────────────────
        if (! Schema::hasTable('acc_bank_feed_transactions')) {
            Schema::create('acc_bank_feed_transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('feed_id')->nullable();
                $table->string('external_id')->nullable();
                $table->date('date');
                $table->decimal('amount', 15, 4)->default(0);
                $table->text('description')->nullable();
                $table->string('category')->nullable();
                $table->string('merchant')->nullable();
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->string('ai_category_suggestion')->nullable();
                $table->timestamps();
            });
        }

        // ── 20. acc_openbanking_connections ───────────────────────────────
        if (! Schema::hasTable('acc_openbanking_connections')) {
            Schema::create('acc_openbanking_connections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->string('provider')->nullable(); // gocardless | plaid | bridge
                $table->text('access_token')->nullable();  // encrypted
                $table->text('refresh_token')->nullable(); // encrypted
                $table->timestamp('token_expires_at')->nullable();
                $table->string('requisition_id')->nullable();
                $table->string('status')->default('active');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 21. acc_openbanking_sync_logs ──────────────────────────────────
        if (! Schema::hasTable('acc_openbanking_sync_logs')) {
            Schema::create('acc_openbanking_sync_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('connection_id')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->integer('transactions_fetched')->default(0);
                $table->string('status')->default('ok');
                $table->text('error_message')->nullable();
                $table->timestamps();
            });
        }

        // ── 22. acc_reconciliations ────────────────────────────────────────
        if (! Schema::hasTable('acc_reconciliations')) {
            Schema::create('acc_reconciliations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('gl_account_id')->nullable();
                $table->date('reconciliation_date');
                $table->decimal('book_balance', 15, 4)->default(0);
                $table->decimal('bank_balance', 15, 4)->default(0);
                $table->decimal('difference', 15, 4)->default(0);
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('reconciled_by')->nullable();
                $table->timestamp('reconciled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 23. acc_reconciliation_sessions ───────────────────────────────
        if (! Schema::hasTable('acc_reconciliation_sessions')) {
            Schema::create('acc_reconciliation_sessions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status')->default('open');
                $table->decimal('opening_balance', 15, 4)->default(0);
                $table->decimal('closing_balance', 15, 4)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 24. acc_vat_rates ──────────────────────────────────────────────
        if (! Schema::hasTable('acc_vat_rates')) {
            Schema::create('acc_vat_rates', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->decimal('rate', 8, 4)->default(0);
                $table->string('country_code', 10)->nullable();
                $table->date('applies_from')->nullable();
                $table->date('applies_to')->nullable();
                $table->string('type')->default('standard'); // standard | reduced | zero | exempt
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 25. acc_vat_declarations ───────────────────────────────────────
        if (! Schema::hasTable('acc_vat_declarations')) {
            Schema::create('acc_vat_declarations', function (Blueprint $table): void {
                $table->id();
                $table->string('period_type')->default('monthly'); // monthly | quarterly
                $table->integer('period_year');
                $table->integer('period_number'); // month or quarter number
                $table->string('status')->default('draft'); // draft | submitted | validated
                $table->decimal('total_sales', 15, 4)->default(0);
                $table->decimal('total_purchases', 15, 4)->default(0);
                $table->decimal('vat_collected', 15, 4)->default(0);
                $table->decimal('vat_deductible', 15, 4)->default(0);
                $table->decimal('vat_due', 15, 4)->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->string('reference')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 26. acc_tax_rates ──────────────────────────────────────────────
        if (! Schema::hasTable('acc_tax_rates')) {
            Schema::create('acc_tax_rates', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->decimal('rate', 8, 4)->default(0);
                $table->string('type')->default('vat'); // vat | withholding | corporate | custom
                $table->string('country', 10)->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_compound')->default(false);
                $table->string('applies_to')->nullable(); // goods | services | all
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 27. acc_tax_entries ────────────────────────────────────────────
        if (! Schema::hasTable('acc_tax_entries')) {
            Schema::create('acc_tax_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tax_rate_id')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->decimal('taxable_amount', 15, 4)->default(0);
                $table->decimal('tax_amount', 15, 4)->default(0);
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('type')->default('collected'); // collected | paid
                $table->timestamps();
            });
        }

        // ── 28. acc_tax_settings ───────────────────────────────────────────
        if (! Schema::hasTable('acc_tax_settings')) {
            Schema::create('acc_tax_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('tax_name');
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->string('tax_type')->nullable();
                $table->unsignedBigInteger('gl_account_id')->nullable();
                $table->string('status')->default('active');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 29. acc_cash_flow_forecasts ────────────────────────────────────
        if (! Schema::hasTable('acc_cash_flow_forecasts')) {
            Schema::create('acc_cash_flow_forecasts', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('base_date');
                $table->integer('horizon')->default(90); // days
                $table->date('end_date')->nullable();
                $table->string('scenario')->default('base'); // base | optimistic | pessimistic
                $table->decimal('opening_balance', 15, 4)->default(0);
                $table->decimal('projected_closing_balance', 15, 4)->default(0);
                $table->decimal('minimum_balance_threshold', 15, 4)->default(0);
                $table->text('assumptions')->nullable(); // JSON as text
                $table->string('status')->default('draft');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 30. acc_cash_flow_forecast_items ──────────────────────────────
        if (! Schema::hasTable('acc_cash_flow_forecast_items')) {
            Schema::create('acc_cash_flow_forecast_items', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('forecast_id')->nullable();
                $table->date('date');
                $table->string('category')->nullable();
                $table->string('type')->default('inflow'); // inflow | outflow
                $table->string('source')->nullable();
                $table->text('description')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->decimal('probability', 5, 2)->default(100);
                $table->decimal('weighted_amount', 15, 4)->default(0);
                $table->boolean('is_actual')->default(false);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->timestamps();
            });
        }

        // ── 31. acc_treasury_forecasts ─────────────────────────────────────
        if (! Schema::hasTable('acc_treasury_forecasts')) {
            Schema::create('acc_treasury_forecasts', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status')->default('draft');
                $table->decimal('opening_balance', 15, 4)->default(0);
                $table->decimal('total_inflows', 15, 4)->default(0);
                $table->decimal('total_outflows', 15, 4)->default(0);
                $table->decimal('closing_balance', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 32. acc_treasury_lines ─────────────────────────────────────────
        if (! Schema::hasTable('acc_treasury_lines')) {
            Schema::create('acc_treasury_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('forecast_id')->nullable();
                $table->string('category')->nullable();
                $table->string('flow_type')->default('inflow'); // inflow | outflow
                $table->decimal('amount', 15, 4)->default(0);
                $table->text('description')->nullable();
                $table->date('expected_date')->nullable();
                $table->boolean('is_recurring')->default(false);
                $table->string('recurrence_period')->nullable(); // monthly | weekly | daily
                $table->decimal('probability', 5, 2)->default(100);
                $table->decimal('actual_amount', 15, 4)->nullable();
                $table->timestamps();
            });
        }

        // ── 33. acc_treasury_alerts ────────────────────────────────────────
        if (! Schema::hasTable('acc_treasury_alerts')) {
            Schema::create('acc_treasury_alerts', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('type')->default('low_balance'); // low_balance | high_balance | large_outflow | negative_forecast
                $table->decimal('threshold_amount', 15, 4)->default(0);
                $table->integer('days_lookahead')->default(30);
                $table->string('severity')->default('warning'); // info | warning | critical
                $table->boolean('is_active')->default(true);
                $table->text('notification_channels')->nullable(); // JSON as text
                $table->timestamp('last_triggered_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 34. acc_treasury_scenarios ─────────────────────────────────────
        if (! Schema::hasTable('acc_treasury_scenarios')) {
            Schema::create('acc_treasury_scenarios', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('forecast_id')->nullable();
                $table->string('name');
                $table->string('type')->default('base'); // base | optimistic | pessimistic
                $table->decimal('adjustment_factor', 8, 4)->default(1);
                $table->decimal('scenario_balance', 15, 4)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // ── 35. acc_fixed_assets ───────────────────────────────────────────
        if (! Schema::hasTable('acc_fixed_assets')) {
            Schema::create('acc_fixed_assets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('asset_code')->nullable()->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('asset_class')->nullable(); // equipment | vehicle | building | etc.
                $table->date('acquisition_date')->nullable();
                $table->decimal('acquisition_cost', 15, 4)->default(0);
                $table->decimal('salvage_value', 15, 4)->default(0);
                $table->integer('useful_life_years')->default(5);
                $table->string('depreciation_method')->default('straight_line'); // straight_line | declining_balance | units_of_production
                $table->unsignedBigInteger('asset_account_id')->nullable();
                $table->unsignedBigInteger('depreciation_expense_account_id')->nullable();
                $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable();
                $table->string('status')->default('active'); // active | disposed | fully_depreciated
                $table->date('disposal_date')->nullable();
                $table->decimal('disposal_proceeds', 15, 4)->nullable();
                $table->text('disposal_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 36. acc_asset_depreciation ─────────────────────────────────────
        if (! Schema::hasTable('acc_asset_depreciation')) {
            Schema::create('acc_asset_depreciation', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('asset_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->integer('period_month')->nullable();
                $table->integer('period_year')->nullable();
                $table->decimal('depreciation_amount', 15, 4)->default(0);
                $table->decimal('accumulated_depreciation', 15, 4)->default(0);
                $table->decimal('net_book_value', 15, 4)->default(0);
                $table->decimal('units_used', 15, 4)->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->timestamps();
            });
        }

        // ── 37. acc_asset_disposals ────────────────────────────────────────
        if (! Schema::hasTable('acc_asset_disposals')) {
            Schema::create('acc_asset_disposals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('asset_id')->nullable();
                $table->date('disposal_date');
                $table->string('disposal_type')->nullable(); // sale | scrap | donation
                $table->decimal('disposal_proceeds', 15, 4)->default(0);
                $table->decimal('net_book_value_at_disposal', 15, 4)->default(0);
                $table->decimal('gain_loss', 15, 4)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // ── 38. acc_exchange_rates ─────────────────────────────────────────
        if (! Schema::hasTable('acc_exchange_rates')) {
            Schema::create('acc_exchange_rates', function (Blueprint $table): void {
                $table->id();
                $table->string('base_currency', 10);
                $table->string('target_currency', 10);
                $table->decimal('rate', 15, 6);
                $table->string('source')->nullable(); // ecb | bceao | manual
                $table->date('date');
                $table->timestamps();

                $table->unique(['base_currency', 'target_currency', 'date']);
            });
        }

        // ── 39. acc_currency_gains_losses ─────────────────────────────────
        if (! Schema::hasTable('acc_currency_gains_losses')) {
            Schema::create('acc_currency_gains_losses', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->decimal('original_amount', 15, 4)->default(0);
                $table->string('original_currency', 10)->nullable();
                $table->decimal('converted_amount', 15, 4)->default(0);
                $table->string('base_currency', 10)->nullable();
                $table->decimal('gain_loss', 15, 4)->default(0);
                $table->boolean('realized')->default(false);
                $table->timestamps();
            });
        }

        // ── 40. acc_companies ─────────────────────────────────────────────
        if (! Schema::hasTable('acc_companies')) {
            Schema::create('acc_companies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->unsignedBigInteger('parent_company_id')->nullable();
                $table->string('company_type')->default('subsidiary'); // subsidiary | parent | associate
                $table->decimal('ownership_percentage', 5, 2)->default(100);
                $table->string('currency', 10)->default('XOF');
                $table->integer('fiscal_year_start_month')->default(1);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('elimination_account_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('parent_company_id')
                    ->references('id')
                    ->on('acc_companies')
                    ->onDelete('set null');
            });
        }

        // ── 41. acc_intercompany_transactions ──────────────────────────────
        if (! Schema::hasTable('acc_intercompany_transactions')) {
            Schema::create('acc_intercompany_transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consolidation_group_id')->nullable();
                $table->unsignedBigInteger('from_company_id')->nullable();
                $table->unsignedBigInteger('to_company_id')->nullable();
                $table->string('transaction_type')->nullable();
                $table->date('transaction_date');
                $table->string('reference_number')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->decimal('exchange_rate', 15, 6)->default(1);
                $table->boolean('is_eliminated')->default(false);
                $table->text('elimination_notes')->nullable();
                $table->unsignedBigInteger('related_transaction_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 42. acc_consolidation_reports ─────────────────────────────────
        if (! Schema::hasTable('acc_consolidation_reports')) {
            Schema::create('acc_consolidation_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consolidation_group_id')->nullable();
                $table->string('report_type')->nullable(); // balance_sheet | income_statement | cash_flow
                $table->string('reporting_currency', 10)->default('XOF');
                $table->text('consolidated_data')->nullable();         // JSON as text
                $table->text('intercompany_eliminations')->nullable(); // JSON as text
                $table->text('exchange_differences')->nullable();      // JSON as text
                $table->decimal('total_adjustments', 15, 4)->default(0);
                $table->string('status')->default('draft');
                $table->text('auditor_notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 43. acc_invoice_payments ───────────────────────────────────────
        if (! Schema::hasTable('acc_invoice_payments')) {
            Schema::create('acc_invoice_payments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->date('payment_date');
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->string('payment_method')->nullable(); // bank | cash | mobile_money | cheque
                $table->string('reference')->nullable();
                $table->string('status')->default('completed');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // ── 44. acc_report_templates ───────────────────────────────────────
        if (! Schema::hasTable('acc_report_templates')) {
            Schema::create('acc_report_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('type')->nullable(); // balance_sheet | income_statement | cashflow | custom
                $table->text('description')->nullable();
                $table->text('config')->nullable();    // JSON as text
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 45. acc_saved_report_parameters ───────────────────────────────
        if (! Schema::hasTable('acc_saved_report_parameters')) {
            Schema::create('acc_saved_report_parameters', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('name');
                $table->text('parameters')->nullable(); // JSON as text
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // ── 46. acc_scheduled_reports ──────────────────────────────────────
        if (! Schema::hasTable('acc_scheduled_reports')) {
            Schema::create('acc_scheduled_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('name');
                $table->string('frequency')->default('monthly'); // daily | weekly | monthly | quarterly
                $table->string('format')->default('pdf'); // pdf | excel | csv
                $table->text('recipients')->nullable(); // JSON array as text
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 47. acc_audit_logs ─────────────────────────────────────────────
        if (! Schema::hasTable('acc_audit_logs')) {
            Schema::create('acc_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->string('event');  // created | updated | deleted | posted | approved
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('old_values')->nullable(); // JSON as text
                $table->text('new_values')->nullable(); // JSON as text
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->index(['auditable_type', 'auditable_id']);
            });
        }

        // ── 48. accounting_invoice_approvals ──────────────────────────────
        if (! Schema::hasTable('accounting_invoice_approvals')) {
            Schema::create('accounting_invoice_approvals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->string('invoice_type')->default('invoice');
                $table->string('invoice_number')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->string('status')->default('pending'); // pending | approved | rejected | cancelled
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->text('approval_chain')->nullable(); // JSON as text
                $table->integer('current_level')->default(1);
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 49. accounting_approval_steps ─────────────────────────────────
        if (! Schema::hasTable('accounting_approval_steps')) {
            Schema::create('accounting_approval_steps', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('approval_id')->nullable();
                $table->integer('level')->default(1);
                $table->string('required_role')->nullable();
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->string('action')->nullable(); // approved | rejected | delegated
                $table->text('comment')->nullable();
                $table->decimal('threshold_amount', 15, 4)->nullable();
                $table->timestamps();
            });
        }

        // ── 50. accounting_payment_schedules ──────────────────────────────
        if (! Schema::hasTable('accounting_payment_schedules')) {
            Schema::create('accounting_payment_schedules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->date('due_date');
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->string('payment_method')->nullable();
                $table->string('status')->default('scheduled');
                $table->timestamp('reminder_sent_at')->nullable();
                $table->unsignedBigInteger('calendar_event_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 51. asset_impairments ─────────────────────────────────────────
        if (! Schema::hasTable('asset_impairments')) {
            Schema::create('asset_impairments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('fixed_asset_id')->nullable();
                $table->date('impairment_date');
                $table->decimal('original_cost', 15, 4)->default(0);
                $table->decimal('accumulated_depreciation_before', 15, 4)->default(0);
                $table->decimal('book_value_before', 15, 4)->default(0);
                $table->decimal('fair_value', 15, 4)->default(0);
                $table->decimal('impairment_loss', 15, 4)->default(0);
                $table->decimal('new_book_value', 15, 4)->default(0);
                $table->text('impairment_reason')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->string('status')->default('recorded');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 52. depreciation_schedules ────────────────────────────────────
        if (! Schema::hasTable('depreciation_schedules')) {
            Schema::create('depreciation_schedules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('fixed_asset_id')->nullable();
                $table->string('depreciation_method')->default('straight_line');
                $table->integer('useful_life_years')->default(5);
                $table->decimal('residual_value', 15, 4)->default(0);
                $table->date('depreciation_start_date')->nullable();
                $table->date('depreciation_end_date')->nullable();
                $table->decimal('annual_depreciation_amount', 15, 4)->default(0);
                $table->decimal('accumulated_depreciation', 15, 4)->default(0);
                $table->decimal('book_value', 15, 4)->default(0);
                $table->unsignedBigInteger('depreciation_expense_account_id')->nullable();
                $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable();
                $table->string('status')->default('active');
                $table->text('depreciation_method_details')->nullable(); // JSON as text
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 53. depreciation_entries ──────────────────────────────────────
        if (! Schema::hasTable('depreciation_entries')) {
            Schema::create('depreciation_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('depreciation_schedule_id')->nullable();
                $table->date('period_date');
                $table->decimal('depreciation_amount', 15, 4)->default(0);
                $table->decimal('accumulated_depreciation', 15, 4)->default(0);
                $table->decimal('book_value', 15, 4)->default(0);
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('recorded_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // ── 54. depreciation_policies ─────────────────────────────────────
        if (! Schema::hasTable('depreciation_policies')) {
            Schema::create('depreciation_policies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('policy_name');
                $table->string('asset_category')->nullable();
                $table->string('depreciation_method')->default('straight_line');
                $table->integer('default_useful_life_years')->default(5);
                $table->decimal('default_residual_percentage', 5, 2)->default(0);
                $table->string('tax_depreciation_method')->nullable();
                $table->integer('tax_useful_life_years')->nullable();
                $table->text('policy_description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 55. revenue_contracts ─────────────────────────────────────────
        if (! Schema::hasTable('revenue_contracts')) {
            Schema::create('revenue_contracts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->string('contract_number')->nullable()->unique();
                $table->string('contract_type')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->date('contract_date')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('contract_value', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->string('performance_obligation_type')->nullable();
                $table->text('performance_obligations')->nullable();  // JSON as text
                $table->string('revenue_recognition_method')->nullable();
                $table->text('recognition_policy')->nullable();
                $table->boolean('has_variable_consideration')->default(false);
                $table->decimal('variable_consideration_estimate', 15, 4)->nullable();
                $table->string('variable_consideration_method')->nullable();
                $table->date('variable_consideration_constraint_date')->nullable();
                $table->boolean('has_contract_modification')->default(false);
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 56. revenue_recognition_schedules ─────────────────────────────
        if (! Schema::hasTable('revenue_recognition_schedules')) {
            Schema::create('revenue_recognition_schedules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('revenue_contract_id')->nullable();
                $table->date('recognition_date');
                $table->decimal('amount', 15, 4)->default(0);
                $table->decimal('tax_amount', 15, 4)->default(0);
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->unsignedBigInteger('gl_account_id')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('recognized_at')->nullable();
                $table->timestamps();
            });
        }

        // ── 57. contract_liabilities ──────────────────────────────────────
        if (! Schema::hasTable('contract_liabilities')) {
            Schema::create('contract_liabilities', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('revenue_contract_id')->nullable();
                $table->decimal('liability_amount', 15, 4)->default(0);
                $table->decimal('recognized_amount', 15, 4)->default(0);
                $table->decimal('remaining_amount', 15, 4)->default(0);
                $table->string('status')->default('active');
                $table->unsignedBigInteger('deferred_revenue_account_id')->nullable();
                $table->date('expected_recognition_date')->nullable();
                $table->timestamps();
            });
        }

        // ── 58. tax_jurisdictions ─────────────────────────────────────────
        if (! Schema::hasTable('tax_jurisdictions')) {
            Schema::create('tax_jurisdictions', function (Blueprint $table): void {
                $table->id();
                $table->string('jurisdiction_code')->nullable()->unique();
                $table->string('jurisdiction_name');
                $table->string('country_code', 10)->nullable();
                $table->string('region_code')->nullable();
                $table->string('tax_type')->nullable();
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('tax_calculation_method')->nullable();
                $table->text('tax_rules')->nullable();    // JSON as text
                $table->text('exemptions')->nullable();   // JSON as text
                $table->text('filing_requirements')->nullable(); // JSON as text
                $table->date('filing_due_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 59. tax_compliance_rules ──────────────────────────────────────
        if (! Schema::hasTable('tax_compliance_rules')) {
            Schema::create('tax_compliance_rules', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tax_jurisdiction_id')->nullable();
                $table->string('rule_name');
                $table->string('rule_type')->nullable();
                $table->text('rule_conditions')->nullable(); // JSON as text
                $table->text('rule_actions')->nullable();    // JSON as text
                $table->text('description')->nullable();
                $table->boolean('requires_documentation')->default(false);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 60. tax_compliance_reports ────────────────────────────────────
        if (! Schema::hasTable('tax_compliance_reports')) {
            Schema::create('tax_compliance_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('tax_jurisdiction_id')->nullable();
                $table->date('report_period_start');
                $table->date('report_period_end');
                $table->string('status')->default('draft');
                $table->text('tax_data')->nullable();           // JSON as text
                $table->text('compliance_checks')->nullable();  // JSON as text
                $table->text('notes')->nullable();
                $table->decimal('total_tax_liability', 15, 4)->default(0);
                $table->decimal('total_tax_paid', 15, 4)->default(0);
                $table->decimal('tax_due_or_refund', 15, 4)->default(0);
                $table->timestamp('filed_at')->nullable();
                $table->string('filing_reference_number')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 61. tax_filing_templates ──────────────────────────────────────
        if (! Schema::hasTable('tax_filing_templates')) {
            Schema::create('tax_filing_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tax_jurisdiction_id')->nullable();
                $table->string('filing_form_number')->nullable();
                $table->string('filing_type')->nullable();
                $table->text('field_mappings')->nullable();       // JSON as text
                $table->text('calculation_rules')->nullable();    // JSON as text
                $table->text('validation_rules')->nullable();     // JSON as text
                $table->text('filing_instructions')->nullable();
                $table->date('last_updated')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 62. consolidation_hierarchies ─────────────────────────────────
        if (! Schema::hasTable('consolidation_hierarchies')) {
            Schema::create('consolidation_hierarchies', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type')->nullable();
                $table->unsignedBigInteger('parent_company_id')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->decimal('ownership_percentage', 5, 2)->default(100);
                $table->date('effective_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('metadata')->nullable(); // JSON as text
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 63. consolidation_periods ─────────────────────────────────────
        if (! Schema::hasTable('consolidation_periods')) {
            Schema::create('consolidation_periods', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consolidation_hierarchy_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('frequency')->default('annual'); // monthly | quarterly | annual
                $table->string('status')->default('open');
                $table->text('notes')->nullable();
                $table->timestamp('consolidated_at')->nullable();
                $table->unsignedBigInteger('consolidated_by')->nullable();
                $table->text('consolidation_rules')->nullable(); // JSON as text
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 64. consolidation_entries ─────────────────────────────────────
        if (! Schema::hasTable('consolidation_entries')) {
            Schema::create('consolidation_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consolidation_period_id')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('gl_account_id')->nullable();
                $table->decimal('opening_balance', 15, 4)->default(0);
                $table->decimal('debit_amount', 15, 4)->default(0);
                $table->decimal('credit_amount', 15, 4)->default(0);
                $table->decimal('closing_balance', 15, 4)->default(0);
                $table->decimal('consolidation_adjustment', 15, 4)->default(0);
                $table->decimal('consolidated_amount', 15, 4)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // ── 65. consolidation_eliminations ────────────────────────────────
        if (! Schema::hasTable('consolidation_eliminations')) {
            Schema::create('consolidation_eliminations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('consolidation_hierarchy_id')->nullable();
                $table->unsignedBigInteger('consolidation_period_id')->nullable();
                $table->string('elimination_type')->nullable();
                $table->unsignedBigInteger('gl_account_id')->nullable();
                $table->decimal('debit_amount', 15, 4)->default(0);
                $table->decimal('credit_amount', 15, 4)->default(0);
                $table->text('description')->nullable();
                $table->string('calculation_method')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->timestamps();
            });
        }

        // ── 66. intercompany_reconciliations ──────────────────────────────
        if (! Schema::hasTable('intercompany_reconciliations')) {
            Schema::create('intercompany_reconciliations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_a_id')->nullable();
                $table->unsignedBigInteger('company_b_id')->nullable();
                $table->date('reconciliation_date');
                $table->decimal('company_a_balance', 15, 4)->default(0);
                $table->decimal('company_b_balance', 15, 4)->default(0);
                $table->decimal('difference', 15, 4)->default(0);
                $table->string('status')->default('pending');
                $table->text('reconciliation_notes')->nullable();
                $table->timestamp('reconciled_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 67. intercompany_clearances ───────────────────────────────────
        if (! Schema::hasTable('intercompany_clearances')) {
            Schema::create('intercompany_clearances', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('sending_company_id')->nullable();
                $table->unsignedBigInteger('receiving_company_id')->nullable();
                $table->date('transaction_date');
                $table->string('transaction_type')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('sending_gl_account_id')->nullable();
                $table->unsignedBigInteger('receiving_gl_account_id')->nullable();
                $table->text('description')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamp('cleared_at')->nullable();
                $table->text('documents')->nullable(); // JSON as text
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 68. cost_categories ───────────────────────────────────────────
        if (! Schema::hasTable('cost_categories')) {
            Schema::create('cost_categories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('code')->nullable();
                $table->string('label');
                $table->text('description')->nullable();
                $table->string('color')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('ohada_account_class')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 69. cost_entries ──────────────────────────────────────────────
        if (! Schema::hasTable('cost_entries')) {
            Schema::create('cost_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('category_code')->nullable();
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->decimal('amount_xof', 15, 4)->default(0);
                $table->string('allocatable_type')->nullable();
                $table->unsignedBigInteger('allocatable_id')->nullable();
                $table->string('source_module')->nullable();
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->text('description')->nullable();
                $table->string('period')->nullable(); // YYYY-MM
                $table->integer('fiscal_year')->nullable();
                $table->string('cost_driver')->nullable();
                $table->decimal('units', 15, 4)->nullable();
                $table->decimal('unit_cost', 15, 4)->nullable();
                $table->boolean('is_estimated')->default(false);
                $table->boolean('is_allocated')->default(false);
                $table->timestamp('allocated_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── 70. cost_rollups ──────────────────────────────────────────────
        if (! Schema::hasTable('cost_rollups')) {
            Schema::create('cost_rollups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('entity_type')->nullable(); // product | project | client | bom
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('entity_name')->nullable();
                $table->string('period')->nullable(); // YYYY-MM
                $table->decimal('capex_total', 15, 4)->default(0);
                $table->decimal('opex_total', 15, 4)->default(0);
                $table->decimal('finex_total', 15, 4)->default(0);
                $table->decimal('riskex_total', 15, 4)->default(0);
                $table->decimal('total_cost', 15, 4)->default(0);
                $table->string('currency', 10)->default('XOF');
                $table->decimal('unit_cost', 15, 4)->nullable();
                $table->decimal('margin', 15, 4)->nullable();
                $table->decimal('margin_pct', 5, 2)->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();
            });
        }

        // ── 71. cost_allocation_keys ──────────────────────────────────────
        if (! Schema::hasTable('cost_allocation_keys')) {
            Schema::create('cost_allocation_keys', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('allocation_method')->nullable(); // headcount | revenue | sqm | equal | custom
                $table->string('source_pool')->nullable();
                $table->string('target_type')->nullable();
                $table->text('formula')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    // -------------------------------------------------------------------------
    // DOWN
    // -------------------------------------------------------------------------
    public function down(): void
    {
        // Drop in reverse dependency order
        $tables = [
            'cost_allocation_keys',
            'cost_rollups',
            'cost_entries',
            'cost_categories',
            'intercompany_clearances',
            'intercompany_reconciliations',
            'consolidation_eliminations',
            'consolidation_entries',
            'consolidation_periods',
            'consolidation_hierarchies',
            'tax_filing_templates',
            'tax_compliance_reports',
            'tax_compliance_rules',
            'tax_jurisdictions',
            'contract_liabilities',
            'revenue_recognition_schedules',
            'revenue_contracts',
            'depreciation_policies',
            'depreciation_entries',
            'depreciation_schedules',
            'asset_impairments',
            'accounting_payment_schedules',
            'accounting_approval_steps',
            'accounting_invoice_approvals',
            'acc_audit_logs',
            'acc_scheduled_reports',
            'acc_saved_report_parameters',
            'acc_report_templates',
            'acc_invoice_payments',
            'acc_consolidation_reports',
            'acc_intercompany_transactions',
            'acc_companies',
            'acc_currency_gains_losses',
            'acc_exchange_rates',
            'acc_asset_disposals',
            'acc_asset_depreciation',
            'acc_fixed_assets',
            'acc_treasury_scenarios',
            'acc_treasury_alerts',
            'acc_treasury_lines',
            'acc_treasury_forecasts',
            'acc_cash_flow_forecast_items',
            'acc_cash_flow_forecasts',
            'acc_tax_settings',
            'acc_tax_entries',
            'acc_tax_rates',
            'acc_vat_declarations',
            'acc_vat_rates',
            'acc_reconciliation_sessions',
            'acc_reconciliations',
            'acc_openbanking_sync_logs',
            'acc_openbanking_connections',
            'acc_bank_feed_transactions',
            'acc_bank_feeds',
            'acc_open_banking_connections',
            'acc_bank_transactions',
            'acc_bank_statements',
            'acc_bank_accounts',
            'acc_budget_scenarios',
            'acc_budget_lines',
            'acc_budgets',
            'acc_expense_reports',
            'acc_expense_lines',
            'acc_expenses',
            'acc_journal_entry_lines',
            'acc_journal_entries',
            'acc_invoice_lines',
            'acc_invoices',
            'acc_chart_of_accounts',
            'acc_gl_accounts',
            'acc_journals',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
