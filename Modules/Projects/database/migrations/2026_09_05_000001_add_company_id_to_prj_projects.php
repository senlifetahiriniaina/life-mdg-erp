<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 10: prj_projects has never had any per-company/tenant column at
 * all — the only place that ever tried to scope portfolio-level project data
 * by tenant (ProjectKpiService::getPortfolioKpis()/getTotalInvoiced()/
 * getOpenRisksCount(), reached via the routed
 * ProjectAdvancedController::portfolioKpis()) queried a `tenant_id` column
 * that was never migrated, so GET /api/v1/projects/portfolio/kpis was a
 * guaranteed QueryException on every real call, not a hypothetical gap.
 * Adds the missing column so that code path can run at all; the rest of
 * Projects' CRUD (ProjectController/TaskController) has never had any
 * per-company isolation concept and is deliberately left untouched here —
 * see CLAUDE.md's Chantier 10 entry for why that's a documented gap rather
 * than silently retrofitted in this pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prj_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('prj_projects', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('owner_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prj_projects', function (Blueprint $table) {
            if (Schema::hasColumn('prj_projects', 'company_id')) {
                $table->dropColumn('company_id');
            }
        });
    }
};
