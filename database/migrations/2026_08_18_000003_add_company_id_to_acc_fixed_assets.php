<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FixedAsset::factory()->for($company) (AccountingEdgeCaseTests /
 * AccountingIntegrationTests, importing App\Models\Company) requires a
 * company_id FK on acc_fixed_assets -- every sibling Accounting model
 * (Budget, Expense, DepreciationPolicy, ...) already carries one; this
 * table was the outlier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acc_fixed_assets') && ! Schema::hasColumn('acc_fixed_assets', 'company_id')) {
            Schema::table('acc_fixed_assets', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('acc_fixed_assets') && Schema::hasColumn('acc_fixed_assets', 'company_id')) {
            Schema::table('acc_fixed_assets', function (Blueprint $table) {
                $table->dropColumn('company_id');
            });
        }
    }
};
