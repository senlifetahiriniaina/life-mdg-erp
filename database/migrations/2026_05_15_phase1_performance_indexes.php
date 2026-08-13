<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM Module - Composite indexes for list/filter queries
        if (Schema::hasTable('crm_contacts')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                if (!Schema::hasIndex('crm_contacts', 'idx_contacts_account_owner_created')) {
                    $table->index(['account_id', 'owner_id', 'created_at'], 'idx_contacts_account_owner_created');
                }
            });
        }

        if (Schema::hasTable('crm_leads')) {
            Schema::table('crm_leads', function (Blueprint $table) {
                if (!Schema::hasIndex('crm_leads', 'idx_leads_status_created_updated')) {
                    $table->index(['status', 'created_at', 'updated_at'], 'idx_leads_status_created_updated');
                }
            });
        }

        if (Schema::hasTable('crm_opportunities')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                if (!Schema::hasIndex('crm_opportunities', 'idx_opportunities_account_stage_amount')) {
                    $table->index(['account_id', 'stage', 'amount'], 'idx_opportunities_account_stage_amount');
                }
            });
        }

        // HR Module - Payroll and employee queries
        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                if (!Schema::hasIndex('hr_employees', 'idx_employees_department_status_hire')) {
                    $table->index(['department_id', 'status', 'hire_date'], 'idx_employees_department_status_hire');
                }
            });
        }

        if (Schema::hasTable('hr_payroll_records')) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                if (!Schema::hasIndex('hr_payroll_records', 'idx_payroll_employee_period_status')) {
                    $table->index(['employee_id', 'period_id', 'status'], 'idx_payroll_employee_period_status');
                }
            });
        }

        // Accounting Module - Invoice and transaction queries
        if (Schema::hasTable('acc_invoices')) {
            Schema::table('acc_invoices', function (Blueprint $table) {
                if (!Schema::hasIndex('acc_invoices', 'idx_invoices_vendor_status_date')) {
                    $table->index(['vendor_id', 'status', 'invoice_date'], 'idx_invoices_vendor_status_date');
                }
            });
        }

        if (Schema::hasTable('acc_transactions')) {
            Schema::table('acc_transactions', function (Blueprint $table) {
                if (!Schema::hasIndex('acc_transactions', 'idx_transactions_account_date_type')) {
                    $table->index(['account_id', 'transaction_date', 'transaction_type'], 'idx_transactions_account_date_type');
                }
            });
        }

        // Inventory Module - Product and movement queries
        if (Schema::hasTable('inventory_products')) {
            Schema::table('inventory_products', function (Blueprint $table) {
                if (!Schema::hasIndex('inventory_products', 'idx_products_warehouse_status_sku')) {
                    $table->index(['warehouse_id', 'status', 'sku'], 'idx_products_warehouse_status_sku');
                }
            });
        }

        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                if (!Schema::hasIndex('inventory_movements', 'idx_movements_product_date_type')) {
                    $table->index(['product_id', 'movement_date', 'movement_type'], 'idx_movements_product_date_type');
                }
            });
        }
    }

    public function down(): void
    {
        // CRM
        if (Schema::hasTable('crm_contacts')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                if (Schema::hasIndex('crm_contacts', 'idx_contacts_account_owner_created')) {
                    $table->dropIndex('idx_contacts_account_owner_created');
                }
            });
        }

        if (Schema::hasTable('crm_leads')) {
            Schema::table('crm_leads', function (Blueprint $table) {
                if (Schema::hasIndex('crm_leads', 'idx_leads_status_created_updated')) {
                    $table->dropIndex('idx_leads_status_created_updated');
                }
            });
        }

        if (Schema::hasTable('crm_opportunities')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                if (Schema::hasIndex('crm_opportunities', 'idx_opportunities_account_stage_amount')) {
                    $table->dropIndex('idx_opportunities_account_stage_amount');
                }
            });
        }

        // HR
        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                if (Schema::hasIndex('hr_employees', 'idx_employees_department_status_hire')) {
                    $table->dropIndex('idx_employees_department_status_hire');
                }
            });
        }

        if (Schema::hasTable('hr_payroll_records')) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                if (Schema::hasIndex('hr_payroll_records', 'idx_payroll_employee_period_status')) {
                    $table->dropIndex('idx_payroll_employee_period_status');
                }
            });
        }

        // Accounting
        if (Schema::hasTable('acc_invoices')) {
            Schema::table('acc_invoices', function (Blueprint $table) {
                if (Schema::hasIndex('acc_invoices', 'idx_invoices_vendor_status_date')) {
                    $table->dropIndex('idx_invoices_vendor_status_date');
                }
            });
        }

        if (Schema::hasTable('acc_transactions')) {
            Schema::table('acc_transactions', function (Blueprint $table) {
                if (Schema::hasIndex('acc_transactions', 'idx_transactions_account_date_type')) {
                    $table->dropIndex('idx_transactions_account_date_type');
                }
            });
        }

        // Inventory
        if (Schema::hasTable('inventory_products')) {
            Schema::table('inventory_products', function (Blueprint $table) {
                if (Schema::hasIndex('inventory_products', 'idx_products_warehouse_status_sku')) {
                    $table->dropIndex('idx_products_warehouse_status_sku');
                }
            });
        }

        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                if (Schema::hasIndex('inventory_movements', 'idx_movements_product_date_type')) {
                    $table->dropIndex('idx_movements_product_date_type');
                }
            });
        }
    }
};
