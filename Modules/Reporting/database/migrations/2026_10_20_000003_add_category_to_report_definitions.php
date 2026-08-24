<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.x hotfix: ReportTemplateSeeder (activated for the first time
 * by wiring it into DatabaseSeeder — see that file's own comment) writes a
 * 'category' field on every one of its 10 report templates (financial/
 * sales/inventory/hr), but report_definitions never had that column —
 * a guaranteed "no such column" SQL error on the very first real seed run.
 * Additive, nullable — matches this module's model/$fillable convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('report_definitions', 'category')) {
            Schema::table('report_definitions', function (Blueprint $table) {
                $table->string('category', 50)->nullable()->after('module');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('report_definitions', 'category')) {
            Schema::table('report_definitions', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
