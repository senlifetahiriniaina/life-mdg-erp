<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Projects module - Timeline queries optimization
        if (Schema::hasTable('prj_tasks')) {
            Schema::table('prj_tasks', function (Blueprint $table) {
                if (!Schema::hasIndex('prj_tasks', 'idx_tasks_project_status_due')) {
                    $table->index(['project_id', 'status', 'due_date'], 'idx_tasks_project_status_due');
                }
            });
        }

        // Accounting module - Report queries optimization
        if (Schema::hasTable('acc_invoices')) {
            Schema::table('acc_invoices', function (Blueprint $table) {
                if (!Schema::hasIndex('acc_invoices', 'idx_invoices_status_date')) {
                    $table->index(['status', 'invoice_date'], 'idx_invoices_status_date');
                }
                if (!Schema::hasIndex('acc_invoices', 'idx_invoices_partner_date')) {
                    $table->index(['partner_id', 'invoice_date'], 'idx_invoices_partner_date');
                }
            });
        }

        // CRM module - Contact and opportunity queries
        if (Schema::hasTable('crm_contacts')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                if (!Schema::hasIndex('crm_contacts', 'idx_contacts_owner_status')) {
                    $table->index(['owner_id', 'status'], 'idx_contacts_owner_status');
                }
            });
        }

        if (Schema::hasTable('crm_opportunities')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                if (!Schema::hasIndex('crm_opportunities', 'idx_opportunities_pipeline_stage')) {
                    $table->index(['pipeline_id', 'stage', 'status'], 'idx_opportunities_pipeline_stage');
                }
                if (!Schema::hasIndex('crm_opportunities', 'idx_opportunities_owner_date')) {
                    $table->index(['owner_id', 'expected_close_date'], 'idx_opportunities_owner_date');
                }
            });
        }

        // Logistics module - Shipment queries
        if (Schema::hasTable('logistics_shipments')) {
            Schema::table('logistics_shipments', function (Blueprint $table) {
                if (!Schema::hasIndex('logistics_shipments', 'idx_shipments_status_date')) {
                    $table->index(['status', 'booked_at'], 'idx_shipments_status_date');
                }
            });
        }

        // HR module - Payroll queries
        if (Schema::hasTable('hr_payroll_records')) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                if (!Schema::hasIndex('hr_payroll_records', 'idx_payroll_employee_period')) {
                    $table->index(['employee_id', 'period_start', 'period_end'], 'idx_payroll_employee_period');
                }
            });
        }

        // Inventory module - Product queries
        Schema::table('inventory_products', function (Blueprint $table) {
            if (Schema::hasTable('inventory_products')) {
                if (!Schema::hasIndex('inventory_products', 'idx_products_status_category')) {
                    $table->index(['status', 'category'], 'idx_products_status_category');
                }
            }
        });

        // AuditLog indexes for performance
        if (Schema::hasTable('core_audit_logs')) {
            Schema::table('core_audit_logs', function (Blueprint $table) {
                if (!Schema::hasIndex('core_audit_logs', 'idx_audit_user_date')) {
                    $table->index(['user_id', 'created_at'], 'idx_audit_user_date');
                }
                if (!Schema::hasIndex('core_audit_logs', 'idx_audit_subject')) {
                    $table->index(['subject_type', 'subject_id'], 'idx_audit_subject');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('prj_tasks', function (Blueprint $table) {
            if (Schema::hasIndex('prj_tasks', 'idx_tasks_project_status_due')) {
                $table->dropIndex('idx_tasks_project_status_due');
            }
        });

        Schema::table('acc_invoices', function (Blueprint $table) {
            if (Schema::hasIndex('acc_invoices', 'idx_invoices_status_date')) {
                $table->dropIndex('idx_invoices_status_date');
            }
            if (Schema::hasIndex('acc_invoices', 'idx_invoices_partner_date')) {
                $table->dropIndex('idx_invoices_partner_date');
            }
        });

        Schema::table('crm_contacts', function (Blueprint $table) {
            if (Schema::hasIndex('crm_contacts', 'idx_contacts_owner_status')) {
                $table->dropIndex('idx_contacts_owner_status');
            }
        });

        Schema::table('crm_opportunities', function (Blueprint $table) {
            if (Schema::hasIndex('crm_opportunities', 'idx_opportunities_pipeline_stage')) {
                $table->dropIndex('idx_opportunities_pipeline_stage');
            }
            if (Schema::hasIndex('crm_opportunities', 'idx_opportunities_owner_date')) {
                $table->dropIndex('idx_opportunities_owner_date');
            }
        });

        Schema::table('logistics_shipments', function (Blueprint $table) {
            if (Schema::hasIndex('logistics_shipments', 'idx_shipments_status_date')) {
                $table->dropIndex('idx_shipments_status_date');
            }
        });

        Schema::table('hr_payroll_records', function (Blueprint $table) {
            if (Schema::hasIndex('hr_payroll_records', 'idx_payroll_employee_period')) {
                $table->dropIndex('idx_payroll_employee_period');
            }
        });

        Schema::table('inventory_products', function (Blueprint $table) {
            if (Schema::hasTable('inventory_products')) {
                if (Schema::hasIndex('inventory_products', 'idx_products_status_category')) {
                    $table->dropIndex('idx_products_status_category');
                }
            }
        });

        Schema::table('core_audit_logs', function (Blueprint $table) {
            if (Schema::hasIndex('core_audit_logs', 'idx_audit_user_date')) {
                $table->dropIndex('idx_audit_user_date');
            }
            if (Schema::hasIndex('core_audit_logs', 'idx_audit_subject')) {
                $table->dropIndex('idx_audit_subject');
            }
        });
    }
};
