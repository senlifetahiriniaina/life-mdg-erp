<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `2026_05_29_000003_create_all_missing_module_tables.php` left `bi_kpi_history`
 * on the generic stub schema (`id/tenant_id/name/config/timestamps/deleted_at`)
 * despite a real Eloquent model (`Modules\BI\Models\KpiHistory`,
 * `$fillable = ['kpi_id','value','recorded_at']`) and a real, live, routed
 * consumer — `Kpi::history()`, eager-loaded by `KpiController::show()`
 * (`GET /api/v1/bi/kpis/{kpi}`). Flagged as an active-breakage landmine in
 * Chantier 9 round 2 but not patched there; closed here (Chantier 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bi_kpi_history')) {
            Schema::table('bi_kpi_history', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_kpi_history', 'kpi_id')) {
                    $table->unsignedBigInteger('kpi_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_kpi_history', 'value')) {
                    $table->decimal('value', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_history', 'recorded_at')) {
                    $table->timestamp('recorded_at')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        // Additive-only patch on a shared stub table — no down(), matching
        // the convention established by the sibling BI stub-table patches.
    }
};
