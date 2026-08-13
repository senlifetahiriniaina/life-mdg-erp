<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_tickets') && ! Schema::hasColumn('hd_tickets', 'assignee_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('assignee_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hd_tickets') && Schema::hasColumn('hd_tickets', 'assignee_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->dropColumn('assignee_id');
            });
        }
    }
};
