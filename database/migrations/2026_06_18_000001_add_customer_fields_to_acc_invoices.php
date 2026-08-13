<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acc_invoices')) {
            return;
        }

        Schema::table('acc_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('acc_invoices', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('partner_name');
            }
            if (! Schema::hasColumn('acc_invoices', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_name');
            }
            if (! Schema::hasColumn('acc_invoices', 'customer_phone')) {
                $table->string('customer_phone')->nullable()->after('customer_email');
            }
            if (! Schema::hasColumn('acc_invoices', 'customer_address')) {
                $table->string('customer_address')->nullable()->after('customer_phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('acc_invoices')) {
            return;
        }

        Schema::table('acc_invoices', function (Blueprint $table) {
            foreach (['customer_name', 'customer_email', 'customer_phone', 'customer_address'] as $column) {
                if (Schema::hasColumn('acc_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
