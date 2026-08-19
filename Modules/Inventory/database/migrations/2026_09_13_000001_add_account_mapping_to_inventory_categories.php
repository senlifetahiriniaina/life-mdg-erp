<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 17 — chart-of-accounts routing per product category, so a
 * product created from a template can show the accountant which stock/
 * purchase/sale account it should post against, without hand-mapping SKUs
 * one by one. Stores account *codes* (e.g. '310'), not FK ids — matches the
 * rest of this app's convention (Chantier 15's acc_operation_templates,
 * Chantier 8.1's OHADA_ACCOUNT_MAP) of routing by the real, human-readable
 * SYSCOHADA-adapted code rather than a foreign key into a chart that only
 * exists once Accounting's seeder has run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_categories', function (Blueprint $table) {
            $table->string('default_stock_account_code', 20)->nullable()->after('image');
            $table->string('default_purchase_account_code', 20)->nullable()->after('default_stock_account_code');
            $table->string('default_sale_account_code', 20)->nullable()->after('default_purchase_account_code');
            $table->string('default_variance_account_code', 20)->nullable()->after('default_sale_account_code');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_categories', function (Blueprint $table) {
            $table->dropColumn([
                'default_stock_account_code',
                'default_purchase_account_code',
                'default_sale_account_code',
                'default_variance_account_code',
            ]);
        });
    }
};
