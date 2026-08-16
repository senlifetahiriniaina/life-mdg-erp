<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive patch for `strategy_ratio_snapshots` (created in
 * 2026_08_16_000005_create_strategy_tables.php).
 *
 * StrategyRatioService::storeSnapshot()/getHistory() need a stateless,
 * module+ratio_key-keyed snapshot API (tenantId/module/ratioKey/value/
 * benchmark/status) that doesn't require a pre-existing `strategy_ratios`
 * row — the original table only supported the ratio_id-linked flow used by
 * Ratio::snapshots(). `ratio_id` is relaxed to nullable so both flows can
 * coexist on the same table.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('strategy_ratio_snapshots')) {
            Schema::table('strategy_ratio_snapshots', function (Blueprint $table) {
                if (! Schema::hasColumn('strategy_ratio_snapshots', 'module')) {
                    $table->string('module')->nullable()->after('ratio_id');
                }
                if (! Schema::hasColumn('strategy_ratio_snapshots', 'ratio_key')) {
                    $table->string('ratio_key')->nullable()->after('module');
                }
                if (! Schema::hasColumn('strategy_ratio_snapshots', 'status')) {
                    $table->string('status')->nullable()->after('gap');
                }
            });

            Schema::table('strategy_ratio_snapshots', function (Blueprint $table) {
                $table->unsignedBigInteger('ratio_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('strategy_ratio_snapshots')) {
            Schema::table('strategy_ratio_snapshots', function (Blueprint $table) {
                $table->dropColumn(['module', 'ratio_key', 'status']);
            });
        }
    }
};
