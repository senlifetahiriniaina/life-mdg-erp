<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * report_shares.tenant_id/created_by are NOT NULL with no default, but
 * neither is in Modules\Reporting\Models\ReportShare's $fillable, and
 * nothing in the module (ReportingController::shareReport(), the model
 * itself) ever sets them -- ReportShare::scopeForTenant() derives tenant
 * scoping via the related report instead
 * (whereHas('report', fn ($q) => $q->where('tenant_id', ...))), so
 * tenant_id was never actually load-bearing here. Same "column the
 * migration has but the model never fills" pattern already fixed for
 * report_definitions' data_source/created_by columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('report_shares', 'tenant_id')) {
            Schema::table('report_shares', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        if (Schema::hasColumn('report_shares', 'created_by')) {
            Schema::table('report_shares', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Not reversible without a value to backfill -- these columns
        // stay nullable.
    }
};
