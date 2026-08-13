<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acc_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('acc_invoices', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('partner_id');
            }
            if (!Schema::hasColumn('acc_invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 4)->default(0)->after('amount_paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('acc_invoices', function (Blueprint $table) {
            $table->dropColumn(['customer_id', 'paid_amount']);
        });
    }
};
