<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TimeTrackingProject treats budget_hours as float|null — a project tracked
 * without a fixed hour budget is a normal case (remaining_hours/is_over_budget
 * both fall back to null/false when it's unset) — but the column was created
 * NOT NULL DEFAULT 0, so creating such a project without an explicit
 * budget_hours throws instead of storing null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('time_tracking_projects') && Schema::hasColumn('time_tracking_projects', 'budget_hours')) {
            Schema::table('time_tracking_projects', function (Blueprint $table) {
                $table->decimal('budget_hours', 8, 2)->nullable()->default(null)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('time_tracking_projects') && Schema::hasColumn('time_tracking_projects', 'budget_hours')) {
            Schema::table('time_tracking_projects', function (Blueprint $table) {
                $table->decimal('budget_hours', 8, 2)->default(0)->change();
            });
        }
    }
};
