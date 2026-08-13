<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_tickets') && ! Schema::hasColumn('hd_tickets', 'customer_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hd_tickets') && Schema::hasColumn('hd_tickets', 'customer_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->dropColumn('customer_id');
            });
        }
    }
};
