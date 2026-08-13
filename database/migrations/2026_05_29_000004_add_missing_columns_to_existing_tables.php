<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addColumn(string $table, string $column, \Closure $definition): void
    {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $t) use ($column, $definition) {
                $definition($t);
            });
        }
    }

    public function up(): void
    {
        // ── users ──────────────────────────────────────────────────────────
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $t) {
                if (!Schema::hasColumn('users', 'tenant_id'))     $t->unsignedBigInteger('tenant_id')->nullable()->index();
                if (!Schema::hasColumn('users', 'address'))       $t->text('address')->nullable();
                if (!Schema::hasColumn('users', 'pii_encrypted')) $t->text('pii_encrypted')->nullable();
                if (!Schema::hasColumn('users', 'social_security')) $t->string('social_security')->nullable();
                if (!Schema::hasColumn('users', 'tier'))          $t->string('tier')->nullable();
            });
        }

        // ── tenants ────────────────────────────────────────────────────────
        $this->addColumn('tenants', 'slug', fn($t) => $t->string('slug')->nullable()->unique());

        // ── acc_gl_accounts ────────────────────────────────────────────────
        if (Schema::hasTable('acc_gl_accounts')) {
            Schema::table('acc_gl_accounts', function (Blueprint $t) {
                if (!Schema::hasColumn('acc_gl_accounts', 'code'))      $t->string('code')->nullable();
                if (!Schema::hasColumn('acc_gl_accounts', 'name'))      $t->string('name')->nullable();
                if (!Schema::hasColumn('acc_gl_accounts', 'parent_id')) $t->unsignedBigInteger('parent_id')->nullable();
                if (!Schema::hasColumn('acc_gl_accounts', 'is_active')) $t->boolean('is_active')->default(true);
                if (!Schema::hasColumn('acc_gl_accounts', 'created_by')) $t->unsignedBigInteger('created_by')->nullable();
            });
        }

        // ── acc_invoices ───────────────────────────────────────────────────
        if (Schema::hasTable('acc_invoices')) {
            Schema::table('acc_invoices', function (Blueprint $t) {
                if (!Schema::hasColumn('acc_invoices', 'paid_amount'))    $t->decimal('paid_amount', 15, 4)->default(0);
                if (!Schema::hasColumn('acc_invoices', 'invoice_number')) $t->string('invoice_number')->nullable();
            });
        }

        // ── acc_expenses ───────────────────────────────────────────────────
        if (Schema::hasTable('acc_expenses')) {
            Schema::table('acc_expenses', function (Blueprint $t) {
                if (!Schema::hasColumn('acc_expenses', 'created_by')) $t->unsignedBigInteger('created_by')->nullable();
                if (!Schema::hasColumn('acc_expenses', 'category'))   $t->string('category')->nullable();
            });
        }

        // ── acc_budgets ────────────────────────────────────────────────────
        if (Schema::hasTable('acc_budgets')) {
            Schema::table('acc_budgets', function (Blueprint $t) {
                if (!Schema::hasColumn('acc_budgets', 'created_by'))           $t->unsignedBigInteger('created_by')->nullable();
                if (!Schema::hasColumn('acc_budgets', 'total_revenue_budget'))  $t->decimal('total_revenue_budget', 15, 4)->nullable();
            });
        }

        // ── acc_fixed_assets ──────────────────────────────────────────────
        $this->addColumn('acc_fixed_assets', 'created_by', fn($t) => $t->unsignedBigInteger('created_by')->nullable());

        // ── acc_audit_logs ─────────────────────────────────────────────────
        if (Schema::hasTable('acc_audit_logs')) {
            Schema::table('acc_audit_logs', function (Blueprint $t) {
                if (!Schema::hasColumn('acc_audit_logs', 'entity_type')) $t->string('entity_type')->nullable();
                if (!Schema::hasColumn('acc_audit_logs', 'entity_id'))   $t->unsignedBigInteger('entity_id')->nullable();
                if (!Schema::hasColumn('acc_audit_logs', 'action'))      $t->string('action')->nullable();
                if (!Schema::hasColumn('acc_audit_logs', 'user_id'))     $t->unsignedBigInteger('user_id')->nullable();
            });
        }

        // ── acc_intercompany_transactions ─────────────────────────────────
        if (Schema::hasTable('acc_intercompany_transactions')) {
            Schema::table('acc_intercompany_transactions', function (Blueprint $t) {
                if (!Schema::hasColumn('acc_intercompany_transactions', 'journal_entry_id'))
                    $t->unsignedBigInteger('journal_entry_id')->nullable();
            });
        }

        // ── bi_kpis ────────────────────────────────────────────────────────
        $this->addColumn('bi_kpis', 'metric', fn($t) => $t->string('metric')->nullable());

        // ── core_gdpr_consents ─────────────────────────────────────────────
        if (Schema::hasTable('core_gdpr_consents')) {
            Schema::table('core_gdpr_consents', function (Blueprint $t) {
                if (!Schema::hasColumn('core_gdpr_consents', 'email'))   $t->string('email')->nullable();
                if (!Schema::hasColumn('core_gdpr_consents', 'granted')) $t->boolean('granted')->default(false);
            });
        }

        // ── crm_territories ───────────────────────────────────────────────
        $this->addColumn('crm_territories', 'rules', fn($t) => $t->text('rules')->nullable());

        // ── doc_folders ────────────────────────────────────────────────────
        $this->addColumn('doc_folders', 'created_by', fn($t) => $t->unsignedBigInteger('created_by')->nullable());

        // ── documents ─────────────────────────────────────────────────────
        $this->addColumn('documents', 'created_by', fn($t) => $t->unsignedBigInteger('created_by')->nullable());

        // ── ec_orders ─────────────────────────────────────────────────────
        $this->addColumn('ec_orders', 'stripe_payment_method_id', fn($t) => $t->string('stripe_payment_method_id')->nullable());

        // ── ec_payments ────────────────────────────────────────────────────
        if (Schema::hasTable('ec_payments')) {
            Schema::table('ec_payments', function (Blueprint $t) {
                if (!Schema::hasColumn('ec_payments', 'currency')) $t->string('currency', 10)->default('XOF');
                if (!Schema::hasColumn('ec_payments', 'gateway'))  $t->string('gateway')->nullable();
            });
        }

        // ── ec_products ────────────────────────────────────────────────────
        $this->addColumn('ec_products', 'stock', fn($t) => $t->integer('stock')->default(0));

        // ── email_campaigns ────────────────────────────────────────────────
        $this->addColumn('email_campaigns', 'from_name', fn($t) => $t->string('from_name')->nullable());

        // ── hd_kb_categories ──────────────────────────────────────────────
        $this->addColumn('hd_kb_categories', 'created_by', fn($t) => $t->unsignedBigInteger('created_by')->nullable());

        // ── hd_tickets ─────────────────────────────────────────────────────
        if (Schema::hasTable('hd_tickets')) {
            Schema::table('hd_tickets', function (Blueprint $t) {
                if (!Schema::hasColumn('hd_tickets', 'reporter_id')) $t->unsignedBigInteger('reporter_id')->nullable();
                if (!Schema::hasColumn('hd_tickets', 'channel'))     $t->string('channel')->nullable();
            });
        }

        // ── hr_employees ───────────────────────────────────────────────────
        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $t) {
                if (!Schema::hasColumn('hr_employees', 'annual_leave_balance')) $t->decimal('annual_leave_balance', 8, 2)->default(0);
                if (!Schema::hasColumn('hr_employees', 'full_name'))            $t->string('full_name')->nullable();
            });
        }

        // ── hr_leave_types ─────────────────────────────────────────────────
        if (Schema::hasTable('hr_leave_types')) {
            Schema::table('hr_leave_types', function (Blueprint $t) {
                if (!Schema::hasColumn('hr_leave_types', 'description'))         $t->text('description')->nullable();
                if (!Schema::hasColumn('hr_leave_types', 'is_active'))           $t->boolean('is_active')->default(true);
                if (!Schema::hasColumn('hr_leave_types', 'carry_forward'))       $t->boolean('carry_forward')->default(false);
                if (!Schema::hasColumn('hr_leave_types', 'max_carry_forward_days')) $t->integer('max_carry_forward_days')->default(0);
                if (!Schema::hasColumn('hr_leave_types', 'is_paid'))             $t->boolean('is_paid')->default(true);
                if (!Schema::hasColumn('hr_leave_types', 'days_per_year'))       $t->integer('days_per_year')->default(0);
                if (!Schema::hasColumn('hr_leave_types', 'code'))                $t->string('code')->nullable();
            });
        }

        // ── inventory_products ─────────────────────────────────────────────
        if (Schema::hasTable('inventory_products')) {
            Schema::table('inventory_products', function (Blueprint $t) {
                if (!Schema::hasColumn('inventory_products', 'category'))      $t->string('category')->nullable();
                if (!Schema::hasColumn('inventory_products', 'reorder_level')) $t->integer('reorder_level')->default(0);
                if (!Schema::hasColumn('inventory_products', 'selling_price')) $t->decimal('selling_price', 15, 4)->nullable();
                if (!Schema::hasColumn('inventory_products', 'status'))        $t->string('status')->default('active');
            });
        }

        // ── mfg_bill_of_materials ──────────────────────────────────────────
        $this->addColumn('mfg_bill_of_materials', 'reference', fn($t) => $t->string('reference')->nullable());

        // ── pos_configs ────────────────────────────────────────────────────
        $this->addColumn('pos_configs', 'warehouse_id', fn($t) => $t->unsignedBigInteger('warehouse_id')->nullable());

        // ── push_tokens ────────────────────────────────────────────────────
        if (Schema::hasTable('push_tokens')) {
            Schema::table('push_tokens', function (Blueprint $t) {
                if (!Schema::hasColumn('push_tokens', 'platform')) $t->string('platform')->nullable();
                if (!Schema::hasColumn('push_tokens', 'token'))    $t->string('token', 512)->nullable();
            });
        }

        // ── rate_limit_metrics ─────────────────────────────────────────────
        $this->addColumn('rate_limit_metrics', 'user_id', fn($t) => $t->unsignedBigInteger('user_id')->nullable()->index());

        // ── sync_queue ─────────────────────────────────────────────────────
        if (Schema::hasTable('sync_queue')) {
            Schema::table('sync_queue', function (Blueprint $t) {
                if (!Schema::hasColumn('sync_queue', 'operation'))         $t->string('operation')->nullable();
                if (!Schema::hasColumn('sync_queue', 'entity_type'))       $t->string('entity_type')->nullable();
                if (!Schema::hasColumn('sync_queue', 'entity_id'))         $t->unsignedBigInteger('entity_id')->nullable();
                if (!Schema::hasColumn('sync_queue', 'payload'))           $t->text('payload')->nullable();
                if (!Schema::hasColumn('sync_queue', 'client_timestamp'))  $t->timestamp('client_timestamp')->nullable();
            });
        }

        // ── wa_conversations ───────────────────────────────────────────────
        $this->addColumn('wa_conversations', 'wa_contact_id', fn($t) => $t->unsignedBigInteger('wa_contact_id')->nullable());

        // ── hr_payslip_lines ───────────────────────────────────────────────
        if (Schema::hasTable('hr_payslip_lines')) {
            Schema::table('hr_payslip_lines', function (Blueprint $t) {
                if (!Schema::hasColumn('hr_payslip_lines', 'category'))   $t->string('category')->nullable();
                if (!Schema::hasColumn('hr_payslip_lines', 'is_taxable')) $t->boolean('is_taxable')->default(true);
            });
        }

        // ── hr_payroll_records ─────────────────────────────────────────────
        if (Schema::hasTable('hr_payroll_records')) {
            Schema::table('hr_payroll_records', function (Blueprint $t) {
                if (!Schema::hasColumn('hr_payroll_records', 'total_deductions')) $t->decimal('total_deductions', 15, 4)->nullable();
                if (!Schema::hasColumn('hr_payroll_records', 'currency'))         $t->string('currency', 10)->default('XOF');
                if (!Schema::hasColumn('hr_payroll_records', 'breakdown'))        $t->text('breakdown')->nullable();
                if (!Schema::hasColumn('hr_payroll_records', 'payroll_config_id')) $t->unsignedBigInteger('payroll_config_id')->nullable();
                if (!Schema::hasColumn('hr_payroll_records', 'payment_date'))     $t->date('payment_date')->nullable();
            });
        }
    }

    public function down(): void {}
};
