<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier "CRM tenant-isolation follow-up" (dedicated chantier closing the gap CLAUDE.md's
 * Chantier 19 Lot 1 entry documented and deliberately deferred: "PipelineService,
 * CRMForecastingService, EinsteinForecastingService, ForecastService,
 * OpportunityScoringService, CampaignOrchestrationService all run tenant-unfiltered
 * Opportunity/Contact aggregates, and the Activity/Pipeline tables have no tenant column at
 * all").
 *
 * Confirmed via Schema::hasColumn() before writing this migration: neither crm_activities nor
 * crm_pipelines has ever had a tenant/company column of any kind (their original catch-all/
 * dedicated migrations never scaffolded one — unlike crm_contacts/crm_leads/crm_opportunities/
 * crm_opportunity_scores/crm_scoring_rules/crm_forecasts, which already carry a leftover
 * company_id/tenant_id column from an earlier stub-scaffold pass, just never wired up).
 * crm_campaigns was found to have the identical gap while investigating
 * CampaignOrchestrationService (see that service's own removal note) — its real, live sibling
 * CampaignController/CampaignPolicy have zero company scoping of any kind, a genuine,
 * confirmed cross-tenant hole distinct from (and more severe than) the tenant-unfiltered
 * *aggregate* gap this migration set out to close.
 *
 * Additive nullable `company_id` (unsignedBigInteger, indexed), matching the exact shape
 * already used by crm_accounts' own company_id column (2026_09_15_000001) — the established
 * convention for this module rather than the stancl/tenancy-style string(36) tenant_id used by
 * Security/Integration.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_activities', 'crm_pipelines', 'crm_campaigns'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('company_id')->nullable()->after('id')->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['crm_activities', 'crm_pipelines', 'crm_campaigns'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('company_id');
                });
            }
        }
    }
};
