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
        // ── acc_consolidation_groups ───────────────────────────────────────
        $this->patch('acc_consolidation_groups', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description')) $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'currency'))    $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
        });

        // ── acc_fiscal_years ───────────────────────────────────────────────
        $this->patch('acc_fiscal_years', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))       $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'start_date')) $t->date('start_date')->nullable();
            if (!Schema::hasColumn($table, 'end_date'))   $t->date('end_date')->nullable();
            if (!Schema::hasColumn($table, 'is_closed'))  $t->boolean('is_closed')->default(false);
        });

        // ── acc_budgets (more columns) ────────────────────────────────────
        $this->patch('acc_budgets', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_spent'))          $t->decimal('total_spent', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'department'))           $t->string('department')->nullable();
            if (!Schema::hasColumn($table, 'total_expense_budget')) $t->decimal('total_expense_budget', 15, 4)->nullable();
        });

        // ── acc_budget_lines ──────────────────────────────────────────────
        $this->patch('acc_budget_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'gl_account_id'))  $t->unsignedBigInteger('gl_account_id')->nullable();
            if (!Schema::hasColumn($table, 'budget_id'))      $t->unsignedBigInteger('budget_id')->nullable();
            if (!Schema::hasColumn($table, 'amount'))         $t->decimal('amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'period'))         $t->string('period', 20)->nullable();
            if (!Schema::hasColumn($table, 'actual_amount'))  $t->decimal('actual_amount', 15, 4)->default(0);
        });

        // ── acc_reconciliations ────────────────────────────────────────────
        $this->patch('acc_reconciliations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'bank_account_id'))  $t->unsignedBigInteger('bank_account_id')->nullable();
            if (!Schema::hasColumn($table, 'period_start'))     $t->date('period_start')->nullable();
            if (!Schema::hasColumn($table, 'period_end'))       $t->date('period_end')->nullable();
            if (!Schema::hasColumn($table, 'bank_balance'))     $t->decimal('bank_balance', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'book_balance'))     $t->decimal('book_balance', 15, 4)->default(0);
        });

        // ── acc_intercompany_transactions (more columns) ───────────────────
        $this->patch('acc_intercompany_transactions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'amount'))       $t->decimal('amount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'currency'))     $t->string('currency', 10)->default('XOF');
        });

        // ── acc_gl_accounts (more columns) ────────────────────────────────
        $this->patch('acc_gl_accounts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))  $t->string('type', 20)->nullable();
        });

        // ── acc_invoices (more columns) ────────────────────────────────────
        $this->patch('acc_invoices', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'customer_id'))  $t->unsignedBigInteger('customer_id')->nullable();
        });

        // ── acc_audit_logs (more columns) ─────────────────────────────────
        $this->patch('acc_audit_logs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reason'))  $t->text('reason')->nullable();
        });

        // ── tenants (more columns) ─────────────────────────────────────────
        $this->patch('tenants', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'company_name'))  $t->string('company_name')->nullable();
            if (!Schema::hasColumn($table, 'country'))       $t->string('country', 10)->nullable();
            if (!Schema::hasColumn($table, 'currency'))      $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'plan'))          $t->string('plan', 20)->default('trial');
            if (!Schema::hasColumn($table, 'is_active'))     $t->boolean('is_active')->default(true);
        });

        // ── prj_time_entries ───────────────────────────────────────────────
        $this->patch('prj_time_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'project_id'))  $t->unsignedBigInteger('project_id')->nullable();
            if (!Schema::hasColumn($table, 'task_id'))     $t->unsignedBigInteger('task_id')->nullable();
            if (!Schema::hasColumn($table, 'user_id'))     $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'hours'))       $t->decimal('hours', 6, 2)->default(0);
            if (!Schema::hasColumn($table, 'date'))        $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'description')) $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'billable'))    $t->boolean('billable')->default(false);
        });

        // ── prj_resource_allocations ───────────────────────────────────────
        $this->patch('prj_resource_allocations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'allocation_type'))  $t->string('allocation_type', 20)->default('full');
            if (!Schema::hasColumn($table, 'project_id'))       $t->unsignedBigInteger('project_id')->nullable();
            if (!Schema::hasColumn($table, 'user_id'))          $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'start_date'))       $t->date('start_date')->nullable();
            if (!Schema::hasColumn($table, 'end_date'))         $t->date('end_date')->nullable();
            if (!Schema::hasColumn($table, 'percentage'))       $t->integer('percentage')->default(100);
        });

        // ── prj_project_billing ────────────────────────────────────────────
        $this->patch('prj_project_billing', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'project_id'))    $t->unsignedBigInteger('project_id')->nullable();
            if (!Schema::hasColumn($table, 'billing_type'))  $t->string('billing_type', 20)->default('fixed');
            if (!Schema::hasColumn($table, 'rate'))          $t->decimal('rate', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'amount'))        $t->decimal('amount', 15, 4)->default(0);
        });

        // ── prj_resource_capacity ──────────────────────────────────────────
        $this->patch('prj_resource_capacity', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_id'))         $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'week_start'))      $t->date('week_start')->nullable();
            if (!Schema::hasColumn($table, 'capacity_hours'))  $t->decimal('capacity_hours', 6, 2)->default(40);
            if (!Schema::hasColumn($table, 'allocated_hours')) $t->decimal('allocated_hours', 6, 2)->default(0);
        });

        // ── pos_table_sections ─────────────────────────────────────────────
        $this->patch('pos_table_sections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))       $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'config_id'))  $t->unsignedBigInteger('config_id')->nullable();
            if (!Schema::hasColumn($table, 'capacity'))   $t->integer('capacity')->default(0);
            if (!Schema::hasColumn($table, 'is_active'))  $t->boolean('is_active')->default(true);
        });

        // ── pos_shifts ────────────────────────────────────────────────────
        $this->patch('pos_shifts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cashier_id'))     $t->unsignedBigInteger('cashier_id')->nullable();
            if (!Schema::hasColumn($table, 'config_id'))      $t->unsignedBigInteger('config_id')->nullable();
            if (!Schema::hasColumn($table, 'opened_at'))      $t->timestamp('opened_at')->nullable();
            if (!Schema::hasColumn($table, 'closed_at'))      $t->timestamp('closed_at')->nullable();
            if (!Schema::hasColumn($table, 'opening_cash'))   $t->decimal('opening_cash', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'closing_cash'))   $t->decimal('closing_cash', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'total_sales'))    $t->decimal('total_sales', 15, 4)->default(0);
        });

        // ── pos_configs (more columns) ────────────────────────────────────
        $this->patch('pos_configs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'currency'))    $t->string('currency', 10)->default('XOF');
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))   $t->boolean('is_active')->default(true);
        });

        // ── pos_payment_terminals ─────────────────────────────────────────
        $this->patch('pos_payment_terminals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))       $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'type'))       $t->string('type', 20)->nullable();
            if (!Schema::hasColumn($table, 'config_id'))  $t->unsignedBigInteger('config_id')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))  $t->boolean('is_active')->default(true);
        });

        // ── quality_issues ────────────────────────────────────────────────
        $this->patch('quality_issues', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'issue_code'))  $t->string('issue_code')->nullable();
            if (!Schema::hasColumn($table, 'title'))       $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'description')) $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'severity'))    $t->string('severity', 20)->default('medium');
            if (!Schema::hasColumn($table, 'product_id'))  $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'reported_by')) $t->unsignedBigInteger('reported_by')->nullable();
            if (!Schema::hasColumn($table, 'resolved_at')) $t->timestamp('resolved_at')->nullable();
        });

        // ── quality_inspections ───────────────────────────────────────────
        $this->patch('quality_inspections', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'inspection_code')) $t->string('inspection_code')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))      $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'inspector_id'))    $t->unsignedBigInteger('inspector_id')->nullable();
            if (!Schema::hasColumn($table, 'result'))          $t->string('result', 20)->default('pending');
            if (!Schema::hasColumn($table, 'notes'))           $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'inspected_at'))    $t->timestamp('inspected_at')->nullable();
        });

        // ── documents ────────────────────────────────────────────────────
        $this->patch('documents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))     $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'mime_type')) $t->string('mime_type')->nullable();
            if (!Schema::hasColumn($table, 'file_path')) $t->text('file_path')->nullable();
            if (!Schema::hasColumn($table, 'file_size')) $t->unsignedBigInteger('file_size')->nullable();
            if (!Schema::hasColumn($table, 'folder_id')) $t->unsignedBigInteger('folder_id')->nullable();
        });

        // ── bi_predictive_models ──────────────────────────────────────────
        $this->patch('bi_predictive_models', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'entity_type'))  $t->string('entity_type')->nullable();
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'algorithm'))    $t->string('algorithm')->nullable();
            if (!Schema::hasColumn($table, 'accuracy'))     $t->decimal('accuracy', 5, 2)->nullable();
            if (!Schema::hasColumn($table, 'trained_at'))   $t->timestamp('trained_at')->nullable();
        });

        // ── wa_chatbot_sessions ───────────────────────────────────────────
        $this->patch('wa_chatbot_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))        $t->string('status', 20)->default('active');
            if (!Schema::hasColumn($table, 'phone_number'))  $t->string('phone_number')->nullable();
            if (!Schema::hasColumn($table, 'messages'))      $t->text('messages')->nullable();
            if (!Schema::hasColumn($table, 'started_at'))    $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'ended_at'))      $t->timestamp('ended_at')->nullable();
        });

        // ── ecommerce_rmas (more columns) ─────────────────────────────────
        $this->patch('ecommerce_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reason_details'))  $t->text('reason_details')->nullable();
        });

        // ── crm_email_sequences (more columns) ────────────────────────────
        $this->patch('crm_email_sequences', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_config'))  $t->text('trigger_config')->nullable();
        });

        // ── hr_job_postings (more columns) ────────────────────────────────
        $this->patch('hr_job_postings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'show_salary'))  $t->boolean('show_salary')->default(false);
        });

        // ── hr_critical_positions ─────────────────────────────────────────
        $this->patch('hr_critical_positions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'succession_plan_id'))  $t->unsignedBigInteger('succession_plan_id')->nullable();
            if (!Schema::hasColumn($table, 'title'))               $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'department'))          $t->string('department')->nullable();
            if (!Schema::hasColumn($table, 'risk_level'))          $t->string('risk_level', 20)->default('medium');
        });

        // ── mfg_work_orders (more columns) ────────────────────────────────
        $this->patch('mfg_work_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))  $t->unsignedBigInteger('product_id')->nullable();
        });

        // ── ecommerce_vendors (more columns) ──────────────────────────────
        $this->patch('ecommerce_vendors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'verified_at'))  $t->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void {}
};
