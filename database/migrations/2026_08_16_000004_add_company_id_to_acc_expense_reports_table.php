<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('acc_expense_reports') && ! Schema::hasColumn('acc_expense_reports', 'company_id')) {
            Schema::table('acc_expense_reports', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('acc_expense_reports', function (Blueprint $table) {
            if (Schema::hasColumn('acc_expense_reports', 'company_id')) {
                $table->dropColumn('company_id');
            }
        });
    }
};
