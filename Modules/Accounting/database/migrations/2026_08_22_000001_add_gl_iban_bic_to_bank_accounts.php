<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acc_bank_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('acc_bank_accounts', 'gl_account_id')) {
                $table->unsignedBigInteger('gl_account_id')->nullable()->after('account_number');
                $table->foreign('gl_account_id')->references('id')->on('acc_chart_of_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('acc_bank_accounts', 'iban')) {
                $table->string('iban', 34)->nullable()->after('account_number');
            }
            if (! Schema::hasColumn('acc_bank_accounts', 'bic')) {
                $table->string('bic', 11)->nullable()->after('iban');
            }
        });
    }

    public function down(): void
    {
        Schema::table('acc_bank_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('acc_bank_accounts', 'gl_account_id')) {
                $table->dropForeign(['gl_account_id']);
                $table->dropColumn('gl_account_id');
            }
            if (Schema::hasColumn('acc_bank_accounts', 'bic')) {
                $table->dropColumn('bic');
            }
            if (Schema::hasColumn('acc_bank_accounts', 'iban')) {
                $table->dropColumn('iban');
            }
        });
    }
};
