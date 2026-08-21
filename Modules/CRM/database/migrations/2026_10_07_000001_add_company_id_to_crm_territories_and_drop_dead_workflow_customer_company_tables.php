<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.15 — CRM 14-layer deep audit.
 *
 * Part 1 (up): `crm_territories` never had a tenant/company column of any kind — confirmed
 * via Schema::getColumnListing() before writing this migration — so TerritoryController's
 * entire surface (list/create/show/update/delete/forecast/rebalance/teamQuotas/coverage/
 * assignOpportunity) had zero cross-tenant isolation: any authenticated CRM-module user of
 * any company could read and mutate any other company's sales territories (region, quota,
 * assigned rep). Additive nullable `company_id` (unsignedBigInteger, indexed), matching the
 * exact shape already used by crm_accounts/crm_activities/crm_pipelines/crm_campaigns'
 * own company_id columns (2026_09_15_000001 / 2026_09_20_000001) rather than the
 * stancl/tenancy-style tenant_id used elsewhere in this module.
 *
 * Part 2 (up): drops the tables backing 3 confirmed dead/fake subsystems found during this
 * same audit (layer 9 — "fake/dead", classified and acted on, not left undecided):
 *   - `crm_workflows`/`crm_workflow_nodes`/`crm_workflow_edges`/`crm_workflow_executions`:
 *     a CRM-scoped no-code workflow builder (WorkflowBuilderController) with real persistence
 *     but literally no execution engine anywhere — activate()/deactivate() only ever flip a
 *     `status` string, no listener/scheduler/job anywhere in the app ever creates a
 *     WorkflowExecution row outside of a factory, and zero Vue page calls any of its routes.
 *     A confirmed duplicate of the real, live `Modules\Workflow` n8n-like engine, which already
 *     registers `crm.contact.created`/`crm.opportunity.won`/`crm.lead.qualified` triggers for
 *     real (`Modules\Workflow\Services\Automation\NodeTypeRegistry`) — same
 *     dead-parallel-subsystem pattern already established this session (CRM's own
 *     TerritoryManagementController at Chantier 8.2, Workflow's own ApprovalWorkflowService at
 *     Chantier 32).
 *   - `crm_customers`: backs `Modules\CRM\Models\Customer`/`CustomerManagementService`, zero
 *     route/controller consumer anywhere, and independently broken —
 *     `getCustomerDetails()` eager-loads `contacts`/`interactions`/`opportunities` relations
 *     that don't exist on the model at all (guaranteed BadMethodCallException the one time it
 *     was ever called outside its own unit test). A confirmed dead duplicate of the real
 *     `App\Models\Customer` already used by Accounting.
 *   - `crm_companies`: backs `Modules\CRM\Models\Company`, zero controller/route anywhere.
 *     Its own `contacts()`/`leads()` relations query `Contact`/`Lead.company_id` — a column
 *     that in every real write path (ContactController::store(), LeadController::store())
 *     actually holds the app's tenant-boundary `App\Models\Company` id, not a
 *     `crm_companies` id — so this relation would silently return wrong-tenant data if it
 *     were ever activated. Confirmed dead and actively wrong, not merely unused.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_territories') && ! Schema::hasColumn('crm_territories', 'company_id')) {
            Schema::table('crm_territories', function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            });
        }

        Schema::dropIfExists('crm_workflow_executions');
        Schema::dropIfExists('crm_workflow_edges');
        Schema::dropIfExists('crm_workflow_nodes');
        Schema::dropIfExists('crm_workflows');
        Schema::dropIfExists('crm_customers');
        Schema::dropIfExists('crm_companies');
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_territories') && Schema::hasColumn('crm_territories', 'company_id')) {
            Schema::table('crm_territories', function (Blueprint $blueprint): void {
                $blueprint->dropColumn('company_id');
            });
        }

        // Dead-table drops are intentionally not reversible here — recreating the exact
        // pre-drop shape of 3 confirmed-dead/broken subsystems on rollback would only
        // resurrect dead weight. Matches the established precedent for prior dead-table-drop
        // migrations this session (e.g. 2026_09_02_000002_drop_asc606_and_mobile_auth_tables).
    }
};
