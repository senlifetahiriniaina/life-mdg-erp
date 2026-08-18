<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 10 finding: CRMForecastingService (30+ methods) and the newly-wired
 * EinsteinForecastingService both filter Opportunity queries by `->where('tenant_id',
 * $tenantId)` — but crm_opportunities never had a tenant_id column at all (confirmed via
 * Schema::hasColumn), unlike its siblings crm_contacts/crm_leads, which already carry one.
 * Every one of those pre-existing forecasting methods has been a guaranteed "Unknown column"
 * SQL error the moment anyone ever called them with a real, non-null/non-zero tenant id —
 * dormant only because nothing in the app passed a real tenantId through before this chantier
 * wired EinsteinForecastingController's endpoints onto real company_id values. Additive
 * nullable column, matching crm_contacts/crm_leads' existing shape exactly.
 *
 * NOTE: this migration only unblocks the forecasting read path. It does NOT retrofit
 * write-side population (OpportunityController::store()/update() still don't set it) or
 * read-side tenant filtering on OpportunityController's own index()/show() — see this
 * chantier's CLAUDE.md entry: the CRM module's core entities (Contact/Account/Lead/
 * Opportunity) have zero cross-tenant isolation on their own CRUD controllers at all, a much
 * larger, separate finding flagged for a dedicated follow-up chantier rather than attempted
 * here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_opportunities') && ! Schema::hasColumn('crm_opportunities', 'tenant_id')) {
            Schema::table('crm_opportunities', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_opportunities') && Schema::hasColumn('crm_opportunities', 'tenant_id')) {
            Schema::table('crm_opportunities', function (Blueprint $table): void {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
