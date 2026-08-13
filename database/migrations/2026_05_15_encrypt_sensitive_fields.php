<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // HR Module - Payroll encryption
        if (Schema::hasTable('hr_payroll_records')) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                if (!Schema::hasColumn('hr_payroll_records', 'gross_salary_encrypted')) {
                    $table->text('gross_salary_encrypted')->nullable()->after('gross_salary');
                }
                if (!Schema::hasColumn('hr_payroll_records', 'total_deductions_encrypted')) {
                    $table->text('total_deductions_encrypted')->nullable()->after('total_deductions');
                }
                if (!Schema::hasColumn('hr_payroll_records', 'net_salary_encrypted')) {
                    $table->text('net_salary_encrypted')->nullable()->after('net_salary');
                }
            });
        }

        // Logistics Module - Address encryption
        if (Schema::hasTable('logistics_shipments')) {
            Schema::table('logistics_shipments', function (Blueprint $table) {
                if (!Schema::hasColumn('logistics_shipments', 'shipper_address_encrypted')) {
                    $table->text('shipper_address_encrypted')->nullable()->after('shipper_address');
                }
                if (!Schema::hasColumn('logistics_shipments', 'consignee_address_encrypted')) {
                    $table->text('consignee_address_encrypted')->nullable()->after('consignee_address');
                }
                if (!Schema::hasColumn('logistics_shipments', 'special_instructions_encrypted')) {
                    $table->text('special_instructions_encrypted')->nullable()->after('special_instructions');
                }
            });
        }

        // Ecommerce Module - Customer PII encryption
        if (Schema::hasTable('ecommerce_customers')) {
            Schema::table('ecommerce_customers', function (Blueprint $table) {
                if (!Schema::hasColumn('ecommerce_customers', 'email_encrypted')) {
                    $table->text('email_encrypted')->nullable()->after('email');
                }
                if (!Schema::hasColumn('ecommerce_customers', 'phone_encrypted')) {
                    $table->text('phone_encrypted')->nullable()->after('phone');
                }
                if (!Schema::hasColumn('ecommerce_customers', 'address_encrypted')) {
                    $table->text('address_encrypted')->nullable()->after('address');
                }
            });
        }

        // Documents Module - Content encryption
        Schema::table('documents_document_contents', function (Blueprint $table) {
            if (Schema::hasTable('documents_document_contents')) {
                if (!Schema::hasColumn('documents_document_contents', 'content_encrypted')) {
                    $table->longText('content_encrypted')->nullable()->after('content');
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_payroll_records')) {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                if (Schema::hasColumn('hr_payroll_records', 'gross_salary_encrypted')) {
                    $table->dropColumn('gross_salary_encrypted');
                }
                if (Schema::hasColumn('hr_payroll_records', 'total_deductions_encrypted')) {
                    $table->dropColumn('total_deductions_encrypted');
                }
                if (Schema::hasColumn('hr_payroll_records', 'net_salary_encrypted')) {
                    $table->dropColumn('net_salary_encrypted');
                }
            });
        }

        if (Schema::hasTable('logistics_shipments')) {
            Schema::table('logistics_shipments', function (Blueprint $table) {
                if (Schema::hasColumn('logistics_shipments', 'shipper_address_encrypted')) {
                    $table->dropColumn('shipper_address_encrypted');
                }
                if (Schema::hasColumn('logistics_shipments', 'consignee_address_encrypted')) {
                    $table->dropColumn('consignee_address_encrypted');
                }
                if (Schema::hasColumn('logistics_shipments', 'special_instructions_encrypted')) {
                    $table->dropColumn('special_instructions_encrypted');
                }
            });
        }

        if (Schema::hasTable('ecommerce_customers')) {
            Schema::table('ecommerce_customers', function (Blueprint $table) {
                if (Schema::hasColumn('ecommerce_customers', 'email_encrypted')) {
                    $table->dropColumn('email_encrypted');
                }
                if (Schema::hasColumn('ecommerce_customers', 'phone_encrypted')) {
                    $table->dropColumn('phone_encrypted');
                }
                if (Schema::hasColumn('ecommerce_customers', 'address_encrypted')) {
                    $table->dropColumn('address_encrypted');
                }
            });
        }

        Schema::table('documents_document_contents', function (Blueprint $table) {
            if (Schema::hasTable('documents_document_contents') && Schema::hasColumn('documents_document_contents', 'content_encrypted')) {
                $table->dropColumn('content_encrypted');
            }
        });
    }
};
