<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19 (Lot 5): `ReportWidget::$fillable`/`$casts` (title, data_source
 * as array/json, config, position_x/position_y/width/height) never matched
 * the real migrated `report_widgets` table at all (real: `name` not `title`,
 * `data_source` a bare `string(100)` — far too small to ever hold the
 * `{module, query, params}` structured payload the code has always written
 * to it as JSON — no `config`/`position_x`/`position_y`/`width`/`height`
 * columns of any kind). Confirmed empirically: every real widget creation,
 * including every one of DashboardService::createDefaultDashboard()/
 * cloneTemplate()'s widget-template loops (the only two places
 * ReportWidget::create() is ever called in this app), has been a guaranteed
 * "no such column" SQL error since this table was written — on top of the
 * separate `dashboards.owner_id` bug fixed alongside this migration, no
 * dashboard has ever been successfully created through this app's real API.
 * Additive, not destructive: the pre-existing `name`/`query_config`/
 * `display_config` columns are left in place (unused going forward, but
 * they never held any real data either — every insert into this table has
 * always failed before reaching them) rather than dropped, matching this
 * session's established non-destructive-migration convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_widgets', function (Blueprint $table) {
            if (! Schema::hasColumn('report_widgets', 'title')) {
                $table->string('title', 200)->nullable()->after('widget_type');
            }
            if (! Schema::hasColumn('report_widgets', 'config')) {
                $table->json('config')->nullable()->after('data_source');
            }
            if (! Schema::hasColumn('report_widgets', 'position_x')) {
                $table->integer('position_x')->default(0)->after('config');
            }
            if (! Schema::hasColumn('report_widgets', 'position_y')) {
                $table->integer('position_y')->default(0)->after('position_x');
            }
            if (! Schema::hasColumn('report_widgets', 'width')) {
                $table->integer('width')->default(1)->after('position_y');
            }
            if (! Schema::hasColumn('report_widgets', 'height')) {
                $table->integer('height')->default(1)->after('width');
            }
        });

        // `data_source` needs to actually hold the {module, query, params}
        // JSON payload ReportWidget::$casts has always claimed it does —
        // widen it from string(100) to a real json column. `name` is the
        // superseded column `title` replaces — was NOT NULL, and the real
        // code has never populated it, so every insert would still fail on
        // this column alone even with every column above added; relaxed
        // rather than dropped, same additive-not-destructive rationale.
        Schema::table('report_widgets', function (Blueprint $table) {
            $table->json('data_source')->nullable()->change();
            $table->string('name', 200)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_widgets', function (Blueprint $table) {
            foreach (['title', 'config', 'position_x', 'position_y', 'width', 'height'] as $col) {
                if (Schema::hasColumn('report_widgets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
