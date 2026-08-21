<?php

namespace Modules\Achats\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Achats\Services\ApprovalRoutingService;

/**
 * Chantier 31 — re-audit of the purchase-order approval chain found that
 * ApprovalRoutingService::createDefaultWorkflows() (the only thing that ever
 * populates a real Modules\Validation\Models\ApprovalWorkflow/ApprovalRule/
 * ApprovalHierarchy/HierarchyLevel/LevelApprover set for module_name='Achats')
 * had NEVER been called from anywhere in the real seed chain — only from
 * this module's own test suite, which calls it directly. Confirmed
 * empirically on a fresh `migrate:fresh --seed`: zero ApprovalWorkflow rows
 * for Achats, so PurchaseOrderService::submitForApproval() always took its
 * `if (! $workflow) { … skip … }` branch — no ApprovalRequest was ever
 * created, no multi-tier escalation ever ran, and approve()/markAsApproved()
 * always finalized a PO in one shot (correctly RBAC-gated via
 * PurchaseOrderPolicy::approve(), but with none of the amount-based
 * escalation ApprovalRoutingService/ApprovalRoutingIntegrationTest.php was
 * built and tested to provide). Wired into the real chain here, matching the
 * precedent already set by Modules\Accounting\Database\Seeders\
 * AccountingDatabaseSeeder — createDefaultWorkflows() is already idempotent
 * (firstOrCreate throughout), so this is a pure wiring fix, no new business
 * logic.
 */
class AchatsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(ApprovalRoutingService::class)->createDefaultWorkflows();
    }
}
