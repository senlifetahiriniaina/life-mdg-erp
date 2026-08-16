<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prj_time_entries')) {
            return;
        }

        Schema::table('prj_time_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('prj_time_entries', 'timesheet_entry_id')) {
                $table->unsignedBigInteger('timesheet_entry_id')->nullable();
            }
            if (!Schema::hasColumn('prj_time_entries', 'source')) {
                $table->string('source', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('prj_time_entries', function (Blueprint $table) {
            if (Schema::hasColumn('prj_time_entries', 'timesheet_entry_id')) {
                $table->dropColumn('timesheet_entry_id');
            }
            if (Schema::hasColumn('prj_time_entries', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
