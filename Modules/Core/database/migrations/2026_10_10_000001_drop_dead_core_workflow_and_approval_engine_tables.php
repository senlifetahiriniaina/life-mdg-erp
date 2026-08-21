<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.1 — deep 14-layer audit of Modules/Core.
 *
 * Drops the tables backing two self-contained generic engines this audit
 * confirmed dead and deleted alongside this migration:
 *
 * 1. core_workflow_definitions/core_workflow_states — Core's own generic
 *    finite-state-machine engine (Modules\Core\Services\WorkflowService,
 *    WorkflowController, WorkflowDefinition/WorkflowState models).
 *    - WorkflowService::getOrCreateState() is the only method anywhere
 *      that ever writes a core_workflow_states row, and it had zero
 *      callers anywhere in the app outside its own class (grep-confirmed)
 *      — no domain module (Accounting/HR/Helpdesk/Projects/CRM/Achats)
 *      ever wired itself onto this engine.
 *    - core/workflows/* (the routes this engine served) had zero real HTTP
 *      caller: zero frontend page anywhere calls it, and zero Pest test
 *      covered any of WorkflowController/WorkflowService/
 *      WorkflowDefinition/WorkflowState.
 *    - The one-time seeder that populated core_workflow_definitions
 *      (database/seeders/WorkflowDefinitionsSeeder.php, also deleted)
 *      targeted 10 resource types, 4 of which (manufacturing/WorkOrder,
 *      manufacturing/ProductionOrder, pos/PosOrder, ecommerce/Order)
 *      belong to modules entirely outside Life MDG's scope (see
 *      CLAUDE.md's "Scope: 28 modules"), and the other 6 (Invoice,
 *      PurchaseOrder, Leave, Ticket, Task, Opportunity) already each have
 *      their own real, live status/state field and their own
 *      domain-specific transition logic.
 *
 * 2. core_approval_workflows/core_approval_instances/core_approval_decisions
 *    — Core's own generic multi-step approval engine
 *    (Modules\Core\Services\ApprovalService, ApprovalController,
 *    ApprovalWorkflow/ApprovalInstance/ApprovalDecision models,
 *    ApprovalWorkflowPolicy). Already flagged by Chantier 31 as having
 *    zero producer (ApprovalService::startApproval() called from nowhere
 *    but its own controller); this audit additionally confirmed its only
 *    2 frontend consumers (ApprovalCard.vue/WorkflowVisualizer.vue) were
 *    never mounted by any real page, and that real approval chains in
 *    this app already go through Modules\Validation (Achats/Accounting/HR
 *    — see CLAUDE.md's Chantier 31 entry).
 *
 * Both were dead-parallel-subsystem duplicates of functionality this app
 * already does live elsewhere — the same pattern already found and deleted
 * repeatedly this session (TerritoryManagementController, the dead
 * Logistics wh_/lgx_ subtree, the Legacy Workflow Engine block in
 * Modules/Workflow, etc) — not landmines
 * worth "activating": activating either would mean building brand-new
 * integration logic never specified anywhere, duplicating state already
 * tracked elsewhere.
 *
 * Not to be confused with Modules\Workflow\Models\WorkflowDefinition
 * (table wfd_definitions) — the real, live, routed n8n-like automation
 * engine — which this migration does not touch at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('core_workflow_states');
        Schema::dropIfExists('core_workflow_definitions');
        Schema::dropIfExists('core_approval_decisions');
        Schema::dropIfExists('core_approval_instances');
        Schema::dropIfExists('core_approval_workflows');
    }

    public function down(): void
    {
        // Deliberately no-op: the dropped tables backed dead, unreachable
        // code that has been deleted alongside this migration — there is
        // nothing left to recreate the schema for.
    }
};
