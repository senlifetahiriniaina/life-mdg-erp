<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        // CRM
        ['crm_contacts',     ['owner_id']],
        ['crm_contacts',     ['status']],
        ['crm_contacts',     ['email']],
        ['crm_leads',        ['owner_id']],
        ['crm_leads',        ['status']],
        ['crm_leads',        ['score']],
        ['crm_opportunities',['owner_id']],
        ['crm_opportunities',['stage']],
        ['crm_opportunities',['close_date']],
        // Accounting
        ['acc_invoices',     ['created_by']],
        ['acc_invoices',     ['status']],
        ['acc_invoices',     ['due_date']],
        ['acc_invoice_lines',['invoice_id', 'product_id']],
        ['acc_journal_entries',['created_by']],
        ['acc_journal_entries',['date']],
        // HR
        ['hr_employees',     ['user_id']],
        ['hr_employees',     ['status']],
        ['hr_employees',     ['department_id']],
        ['hr_payroll_records',['payroll_config_id']],
        ['hr_candidates',    ['stage']],
        ['hr_job_postings',  ['status']],
        ['hr_job_applications',['job_posting_id']],
        ['hr_job_applications',['status']],
        // Helpdesk
        ['hd_tickets',       ['reporter_id']],
        ['hd_tickets',       ['contact_id']],
        ['hd_tickets',       ['status']],
        ['hd_tickets',       ['priority']],
        ['hd_tickets',       ['assigned_to']],
        ['hd_knowledge_base',['author_id']],
        ['hd_knowledge_base',['status']],
        // Inventory
        ['inventory_products',['category_id']],
        ['inventory_products',['status']],
        ['inventory_movements',['product_id']],
        ['inventory_movements',['created_at']],
        ['inventory_suppliers',['status']],
        ['inventory_purchase_orders',['supplier_id']],
        ['inventory_purchase_orders',['status']],
        // Manufacturing
        ['mfg_bom_components',['component_id']],
        ['mfg_routings',     ['workcenter_id']],
        ['mfg_quality_checks',['checked_by']],
        ['mfg_production_orders',['status']],
        ['mfg_production_orders',['scheduled_date']],
        // POS
        ['pos_order_items',  ['order_id', 'product_id']],
        ['pos_tables',       ['status']],
        ['pos_orders',       ['status']],
        ['pos_orders',       ['created_at']],
        // Ecommerce
        ['ec_categories',    ['store_id', 'parent_id']],
        ['ec_products',      ['store_id']],
        ['ec_products',      ['status']],
        ['ec_order_items',   ['product_id']],
        ['ec_orders',        ['status']],
        ['ec_orders',        ['customer_id']],
        // Projects
        ['project_tasks',    ['project_id']],
        ['project_tasks',    ['assignee_id']],
        ['project_tasks',    ['status']],
        ['project_tasks',    ['due_date']],
        // Email
        ['email_subscribers',['status']],
        ['email_campaigns',  ['status']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $missingColumns = array_filter(
                $columns,
                fn ($col) => !Schema::hasColumn($table, $col)
            );
            if ($missingColumns) {
                continue;
            }
            $indexName = $table . '_' . implode('_', $columns) . '_index';
            if (Schema::hasIndex($table, $indexName)) {
                continue;
            }
            Schema::table($table, function (Blueprint $bp) use ($columns, $indexName) {
                $bp->index($columns, $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $columns]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $indexName = $table . '_' . implode('_', $columns) . '_index';
            Schema::table($table, function (Blueprint $bp) use ($indexName) {
                $bp->dropIndexIfExists($indexName);
            });
        }
    }
};
