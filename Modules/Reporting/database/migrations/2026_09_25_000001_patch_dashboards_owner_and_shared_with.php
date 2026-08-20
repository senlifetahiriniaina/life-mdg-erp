<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19 (Lot 5): `Dashboard::$fillable`/`DashboardService` have always
 * written `owner_id`/`shared_with` on every dashboard creation path
 * (storeDashboard()'s bare branch, createDefaultDashboard(), cloneTemplate())
 * — neither column has ever existed on the real `dashboards` table (only
 * `tenant_id/name/description/layout/is_default/is_public/created_by` were
 * ever migrated, confirmed via Schema::getColumnListing against a fresh
 * install). Every real dashboard creation has been a guaranteed "no such
 * column" SQL error since this file was written — the same silently-fatal
 * schema-mismatch bug class documented repeatedly elsewhere in this app
 * (workflow_chain_definitions, the Helpdesk 8-model migration, etc.), just
 * never caught here because no test ever actually created a dashboard
 * through the real service before this chantier's empirical-execution pass.
 *
 * `shared_with` (role-slug array, read by Dashboard::isSharedWithRole()) is
 * added for real, since it's a genuinely different concept from anything
 * that already exists. `owner_id` is NOT re-added as a second column —
 * the real `created_by` column already covers the exact same "who owns this
 * dashboard" concept the code was reaching for under a different name, so
 * the model/service are repointed onto it instead of growing a redundant
 * duplicate. `created_by` is relaxed to nullable, since the default/cloned-
 * template dashboards this service creates are tenant-wide, not owned by
 * one specific user (the code's own prior `'owner_id' => null` intent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            if (! Schema::hasColumn('dashboards', 'shared_with')) {
                $table->json('shared_with')->nullable()->after('is_public');
            }
        });

        Schema::table('dashboards', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            if (Schema::hasColumn('dashboards', 'shared_with')) {
                $table->dropColumn('shared_with');
            }
        });
    }
};
