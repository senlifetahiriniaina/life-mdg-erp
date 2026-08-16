<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * report_executions/report_schedules were migrated against a different
 * schema than Modules\Reporting\Models\ReportExecution/ReportSchedule
 * actually use — same drift class already fixed for report_definitions'
 * own columns in 2026_08_17_000001_patch_report_definitions_columns.php:
 *
 * - report_executions: migration has 'report_id' (model: report_definition_id),
 *   no 'parameters'/'result_data' at all, and 'rows_count' where the model
 *   writes 'result_count'.
 * - report_schedules: migration has 'report_id' (model: report_definition_id),
 *   no 'frequency' column, and 'cron_expression' NOT NULL with no default
 *   while nothing in the module ever sets it.
 *
 * Additive (new column + backfill) rather than a native RENAME COLUMN for
 * the report_id -> report_definition_id part: this repo has several other
 * tables with dangling indexes referencing long-gone column names (found
 * while first attempting a rename here — SQLite's ALTER TABLE ...
 * RENAME COLUMN validates the *entire* schema, not just the table being
 * altered, and trips on those), so avoiding RENAME COLUMN entirely
 * sidesteps that whole unrelated landmine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('report_executions', 'report_id') && ! Schema::hasColumn('report_executions', 'report_definition_id')) {
            Schema::table('report_executions', function (Blueprint $table) {
                $table->unsignedBigInteger('report_definition_id')->nullable()->after('report_id');
            });
            DB::statement('UPDATE report_executions SET report_definition_id = report_id');
        }

        // The model never writes report_id (it only knows report_definition_id), so
        // report_id's original NOT NULL blocks every insert unless relaxed.
        if (Schema::hasColumn('report_executions', 'report_id')) {
            Schema::table('report_executions', function (Blueprint $table) {
                $table->unsignedBigInteger('report_id')->nullable()->change();
            });
        }

        Schema::table('report_executions', function (Blueprint $table) {
            if (! Schema::hasColumn('report_executions', 'parameters')) {
                $table->json('parameters')->nullable()->after('executed_by');
            }
            if (! Schema::hasColumn('report_executions', 'result_count')) {
                $table->integer('result_count')->nullable()->after('status');
            }
            if (! Schema::hasColumn('report_executions', 'result_data')) {
                $table->json('result_data')->nullable()->after('result_count');
            }
        });

        if (Schema::hasColumn('report_schedules', 'report_id') && ! Schema::hasColumn('report_schedules', 'report_definition_id')) {
            Schema::table('report_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('report_definition_id')->nullable()->after('report_id');
            });
            DB::statement('UPDATE report_schedules SET report_definition_id = report_id');
        }

        if (Schema::hasColumn('report_schedules', 'report_id')) {
            Schema::table('report_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('report_id')->nullable()->change();
            });
        }
        if (Schema::hasColumn('report_schedules', 'created_by')) {
            Schema::table('report_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable()->change();
            });
        }

        if (Schema::hasColumn('report_schedules', 'cron_expression')) {
            Schema::table('report_schedules', function (Blueprint $table) {
                $table->string('cron_expression', 100)->nullable()->change();
            });
        }

        Schema::table('report_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('report_schedules', 'frequency')) {
                $table->string('frequency', 20)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('report_executions', 'report_definition_id')) {
            Schema::table('report_executions', function (Blueprint $table) {
                $table->dropColumn('report_definition_id');
            });
        }
        Schema::table('report_executions', function (Blueprint $table) {
            foreach (['parameters', 'result_count', 'result_data'] as $col) {
                if (Schema::hasColumn('report_executions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasColumn('report_schedules', 'report_definition_id')) {
            Schema::table('report_schedules', function (Blueprint $table) {
                $table->dropColumn('report_definition_id');
            });
        }
        Schema::table('report_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('report_schedules', 'frequency')) {
                $table->dropColumn('frequency');
            }
        });
    }
};
