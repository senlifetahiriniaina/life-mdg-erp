<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.9 — 14-layer deep audit of Modules\Settings.
 *
 * Two independent, empirically-confirmed bugs fixed in one migration:
 *
 * 1. Layer 10 (relational/FK) — real, reproducible bug: the original
 *    2026_06_08 migration's unique index was `(tenant_id, key)`, never
 *    including `module` at all (a later, separate patch migration added
 *    `module` as a column but never touched this index). Confirmed via a
 *    real `Setting::set()` call: writing the same key name under two
 *    different modules for the same tenant (e.g. both `crm.theme` and
 *    `accounting.theme`) throws a genuine
 *    UniqueConstraintViolationException on the second write — any
 *    reasonably common key name (theme/enabled/currency/...) reused across
 *    two modules for one tenant is a guaranteed crash, not a hypothetical
 *    edge case. Fixed by widening the unique index to
 *    `(tenant_id, module, key)`, matching what every real write path
 *    (Setting::set()/setTyped()/SettingsService) already keys its
 *    updateOrCreate() lookups on.
 *
 * 2. Layer 9 (fake/dead) — `Modules\Settings\Models\SettingGroup` (table
 *    `setting_groups`) confirmed via repo-wide grep to have ZERO real
 *    consumers anywhere (no controller, no route, no Vue page, not even
 *    referenced by its own factory in any test) — and, independently of
 *    that, genuinely broken as designed: SettingGroup::scopeForModule()
 *    filters `where('module', ...)`, but `setting_groups` has never had a
 *    `module` column (only `name`/`label`/`icon`), a guaranteed "no such
 *    column" SQL error the one time this scope was ever theoretically
 *    called. `settings.group_id` (FK into this dead table) and
 *    `settings.{type,label,is_system}` are the sibling dead columns —
 *    confirmed absent from Setting::$fillable and never referenced by any
 *    real code path (the 2026_08_17 patch migration's own docblock already
 *    explains `type`/`label` as legacy columns superseded by `value_type`/
 *    no-label-concept-at-all). Classified "confirmed dead, delete" per the
 *    Chantier 32 methodology (not "activate" — no real caller anywhere to
 *    build a real grouping UI on top of, and the underlying model is
 *    independently broken regardless).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                if (Schema::hasColumn('settings', 'group_id')) {
                    // SQLite's native ALTER TABLE DROP COLUMN refuses to drop a
                    // column still referenced by an index — drop the composite
                    // (tenant_id, group_id) index first, confirmed via a real
                    // failed migration run before this fix (fell through
                    // cleanly to the pending state, no partial-schema damage,
                    // since SQLite DDL is transactional here).
                    $table->dropIndex('settings_tenant_id_group_id_index');
                    $table->dropForeign(['group_id']);
                    $table->dropColumn('group_id');
                }
                foreach (['type', 'label', 'is_system'] as $deadColumn) {
                    if (Schema::hasColumn('settings', $deadColumn)) {
                        $table->dropColumn($deadColumn);
                    }
                }
            });

            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique('settings_tenant_id_key_unique');
                $table->unique(['tenant_id', 'module', 'key'], 'settings_tenant_id_module_key_unique');
            });
        }

        Schema::dropIfExists('setting_groups');
    }

    public function down(): void
    {
        Schema::create('setting_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 100);
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique('settings_tenant_id_module_key_unique');
                $table->unique(['tenant_id', 'key'], 'settings_tenant_id_key_unique');

                $table->unsignedBigInteger('group_id')->nullable();
                $table->string('type', 30)->default('string');
                $table->string('label', 200)->nullable();
                $table->boolean('is_system')->default(false);

                $table->index(['tenant_id', 'group_id']);
                $table->foreign('group_id')->references('id')->on('setting_groups')->nullOnDelete();
            });
        }
    }
};
