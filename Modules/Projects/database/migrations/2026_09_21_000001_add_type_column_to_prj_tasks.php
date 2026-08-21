<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.17 (Projects deep 14-layer audit, layer 5 — format de
 * données): Modules\Projects\Models\Task::$fillable declares 'type' as a
 * real mass-assignable field (and its own docblock claims
 * `@property string $type`), and the real, live Epics/Index.vue addStory()
 * flow actually sends it ('story' vs the implicit default 'task') — but
 * `prj_tasks` has never had a `type` column at all (confirmed via
 * Schema::getColumnListing()), a guaranteed "no such column" SQL error on
 * every real task creation that sets it, caught empirically while writing
 * this chantier's regression test, not by code reading. Additive, nullable
 * (existing rows have no real type recorded).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prj_tasks')) {
            return;
        }

        Schema::table('prj_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_tasks', 'type')) {
                $table->string('type', 50)->nullable()->after('priority');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prj_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('prj_tasks', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
