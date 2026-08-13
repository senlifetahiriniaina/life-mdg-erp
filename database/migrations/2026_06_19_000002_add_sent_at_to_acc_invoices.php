<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acc_invoices') || Schema::hasColumn('acc_invoices', 'sent_at')) {
            return;
        }

        Schema::table('acc_invoices', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('acc_invoices') && Schema::hasColumn('acc_invoices', 'sent_at')) {
            Schema::table('acc_invoices', function (Blueprint $table) {
                $table->dropColumn('sent_at');
            });
        }
    }
};
