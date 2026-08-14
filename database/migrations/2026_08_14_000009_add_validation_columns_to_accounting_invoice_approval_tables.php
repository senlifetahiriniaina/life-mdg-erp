<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wires acc_invoices/accounting_invoice_approvals into Modules\Validation as
 * a read projection: Validation's validation_approval_requests is the
 * approval state's single source of truth, these columns just let the
 * existing InvoiceApproval/ApprovalStep UI keep reading a fast, invoice-
 * shaped view without re-querying the polymorphic table each time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acc_invoices') && ! Schema::hasColumn('acc_invoices', 'approval_status')) {
            Schema::table('acc_invoices', function (Blueprint $table): void {
                $table->string('approval_status')->nullable()->after('status');
            });
        }

        if (Schema::hasTable('accounting_invoice_approvals') && ! Schema::hasColumn('accounting_invoice_approvals', 'approval_request_id')) {
            Schema::table('accounting_invoice_approvals', function (Blueprint $table): void {
                $table->unsignedBigInteger('approval_request_id')->nullable()->after('invoice_id');
                $table->index('approval_request_id');
            });
        }
    }

    public function down(): void
    {
        // Additive, guard-checked columns — no destructive rollback.
    }
};
