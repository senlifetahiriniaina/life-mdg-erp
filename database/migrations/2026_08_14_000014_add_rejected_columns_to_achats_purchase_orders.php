<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PurchaseOrder had no reject() action at all (only submit/approve/cancel),
 * so ApprovalPanel had to be forced read-only on PurchaseOrders/Show.vue.
 * markAsApproved() records approved_by/approved_at on the PO row itself;
 * markAsRejected() mirrors that with rejected_by/rejected_at so the PO's
 * own row (not just the linked ApprovalRequest's history) shows who
 * rejected it and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('achats_purchase_orders', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('achats_purchase_orders', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('achats_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['rejected_by', 'rejected_at']);
        });
    }
};
