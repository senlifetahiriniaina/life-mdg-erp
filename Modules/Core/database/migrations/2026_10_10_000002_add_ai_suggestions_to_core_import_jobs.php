<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.1 — deep 14-layer audit of Modules/Core.
 *
 * Adds core_import_jobs.ai_suggestions (nullable JSON/text), backing
 * Modules\Core\Services\AiMappingService::suggestMapping()'s output —
 * confirmed to have zero callers anywhere before this chantier despite the
 * real, working, already-built frontend (resources/js/Pages/Import/Index.vue)
 * expecting currentJob.ai_suggestions.column_mapping to pre-fill its mapping
 * UI. See ExtractAndMapImportJob for the wiring and CLAUDE.md's Chantier
 * 32.1 entry for the full finding.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('core_import_jobs', 'ai_suggestions')) {
            return;
        }

        Schema::table('core_import_jobs', function (Blueprint $table) {
            $table->text('ai_suggestions')->nullable()->after('column_mapping');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('core_import_jobs', 'ai_suggestions')) {
            return;
        }

        Schema::table('core_import_jobs', function (Blueprint $table) {
            $table->dropColumn('ai_suggestions');
        });
    }
};
