<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * report_executions.tenant_id and report_schedules.tenant_id/recipients are
 * NOT NULL with no default. Life MDG is a single-tenant deployment (several
 * other "core" tables already have no/nullable tenant_id -- see
 * calendar_calendars, fixed the same way), and legitimate call sites build
 * these rows directly without a tenant_id/recipients list in hand. Relaxing
 * to nullable matches the fix already applied to report_shares in
 * 2026_08_18_000002_relax_report_shares_notnull_columns.php. recipients is
 * only read by Modules\Reporting\Jobs\DeliverScheduledReportJob, which is
 * never dispatched anywhere in production code today, so a null value has
 * no live consumer to break.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('report_executions', 'tenant_id')) {
            Schema::table('report_executions', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        if (Schema::hasColumn('report_schedules', 'tenant_id')) {
            Schema::table('report_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        if (Schema::hasColumn('report_schedules', 'recipients')) {
            Schema::table('report_schedules', function (Blueprint $table) {
                $table->json('recipients')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Not reversible without a value to backfill -- these columns stay nullable.
    }
};
