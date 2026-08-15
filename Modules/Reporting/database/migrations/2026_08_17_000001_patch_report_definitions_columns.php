<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * report_definitions' migration and Modules\Reporting\Models\ReportDefinition
 * were built against two different schemas — the migration has report_type/
 * data_source/query_config/filters/columns_config/sort_config/is_public, the
 * model+factory+tests need slug/module/description/query_template/
 * parameters_schema/output_format/is_active. Additive: old columns kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('report_definitions', 'tenant_id')) {
            Schema::table('report_definitions', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }

        // data_source/created_by aren't in the model's $fillable at all — the
        // model never writes them, but both were NOT NULL with no default.
        if (Schema::hasColumn('report_definitions', 'data_source')) {
            Schema::table('report_definitions', function (Blueprint $table) {
                $table->string('data_source', 100)->nullable()->change();
            });
        }
        if (Schema::hasColumn('report_definitions', 'created_by')) {
            Schema::table('report_definitions', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable()->change();
            });
        }

        Schema::table('report_definitions', function (Blueprint $table) {
            if (! Schema::hasColumn('report_definitions', 'slug')) {
                $table->string('slug', 220)->nullable()->after('name');
            }
            if (! Schema::hasColumn('report_definitions', 'module')) {
                $table->string('module', 50)->nullable()->after('slug');
            }
            if (! Schema::hasColumn('report_definitions', 'description')) {
                $table->text('description')->nullable()->after('module');
            }
            if (! Schema::hasColumn('report_definitions', 'query_template')) {
                $table->text('query_template')->nullable()->after('description');
            }
            if (! Schema::hasColumn('report_definitions', 'parameters_schema')) {
                $table->json('parameters_schema')->nullable()->after('query_template');
            }
            if (! Schema::hasColumn('report_definitions', 'output_format')) {
                $table->string('output_format', 20)->default('table')->after('parameters_schema');
            }
            if (! Schema::hasColumn('report_definitions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_public');
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_definitions', function (Blueprint $table) {
            foreach (['slug', 'module', 'description', 'query_template', 'parameters_schema', 'output_format', 'is_active'] as $col) {
                if (Schema::hasColumn('report_definitions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
