<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19 (CRM re-audit) — headline finding: ContactController/AccountController/
 * LeadController/OpportunityController had zero tenant scoping (documented in CLAUDE.md's
 * Chantier 10 entry as a follow-up-chantier-sized gap, now being closed for real). Contact/Lead
 * already carried a real company_id column; Opportunity got a tenant_id column in the prior
 * chantier. Account had neither — CrmAccountPolicy::sameTenant() compared $user->tenant_id
 * against $model->tenant_id, a phantom user column against a column that never existed on
 * crm_accounts at all, so the comparison was permanently '' === '' (vacuous pass), confirmed
 * empirically via tinker (a user from an unrelated company could both view AND update another
 * company's Account despite the policy's own docblock claiming "Cross-tenant access is
 * denied"). Additive nullable column, matching crm_contacts/crm_leads' existing shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_accounts') && ! Schema::hasColumn('crm_accounts', 'company_id')) {
            Schema::table('crm_accounts', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_accounts') && Schema::hasColumn('crm_accounts', 'company_id')) {
            Schema::table('crm_accounts', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
    }
};
