<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ec_orders') && ! Schema::hasColumn('ec_orders', 'total')) {
            Schema::table('ec_orders', function (Blueprint $table) {
                $table->decimal('total', 15, 2)->nullable();
            });
        }

        if (Schema::hasTable('crm_accounts')) {
            Schema::table('crm_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_accounts', 'status')) {
                    $table->string('status')->nullable();
                }
                if (! Schema::hasColumn('crm_accounts', 'revenue')) {
                    $table->decimal('revenue', 18, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ec_orders') && Schema::hasColumn('ec_orders', 'total')) {
            Schema::table('ec_orders', function (Blueprint $table) {
                $table->dropColumn('total');
            });
        }
        if (Schema::hasTable('crm_accounts')) {
            Schema::table('crm_accounts', function (Blueprint $table) {
                foreach (['status', 'revenue'] as $col) {
                    if (Schema::hasColumn('crm_accounts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
