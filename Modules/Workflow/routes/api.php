<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Workflow\Http\Controllers\Api\WorkflowController;
use Modules\Workflow\Http\Controllers\Api\WorkflowChainController;
use Modules\Workflow\Http\Controllers\Api\WorkflowDefinitionController;
use Modules\Workflow\Http\Controllers\Api\WorkflowExecutionController;
use Modules\Workflow\Http\Controllers\Api\FlowVersionController;
use Modules\Workflow\Http\Controllers\Api\CodeNodeController;

/*
|--------------------------------------------------------------------------
| Workflow Module — API Routes
|--------------------------------------------------------------------------
|
| REST endpoints for workflow definitions, actions, and execution history.
| Phase-39 adds /workflow/definitions (chain engine) and /workflow/executions.
| Legacy routes for the builder UI are preserved below.
|
*/

// Chantier 8.6: this whole group had no module:/role: gate at all — most
// concerning, CodeNodeController::execute() (a sandboxed arbitrary code/
// expression execution endpoint) was reachable by any authenticated user
// of any role. role:manager,admin carries forward the tier the deleted
// legacy block above used to require (the only role hint this file had),
// applied uniformly since every route here mutates or executes workflow
// definitions/code — none of it is pure read-only reporting that would
// justify a looser tier.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Workflow', 'role:manager,admin'])->prefix('v1')->group(function () {

    // ─── DSL & Schema Endpoints (Phase 39) ────────────────────────────────────

    Route::prefix('workflow')->group(function () {
        // DSL parser
        Route::post('/dsl/parse',    [WorkflowController::class, 'dslParse']);
        Route::post('/dsl/validate', [WorkflowController::class, 'dslValidate']);
        Route::post('/dsl/create',   [WorkflowController::class, 'dslCreate']);

        // Schema & catalogue
        Route::get('/schema',   [WorkflowController::class, 'schema']);
        Route::get('/actions',  [WorkflowController::class, 'actions']);
        Route::get('/triggers', [WorkflowController::class, 'triggers']);
    });

    // ─── Workflow Definitions REST API ─────────────────────────────────────────

    Route::prefix('workflows')->group(function () {
        Route::get('/', [WorkflowController::class, 'index']);
        Route::post('/', [WorkflowController::class, 'store']);
        Route::post('/trigger', [WorkflowController::class, 'trigger']);

        Route::get('/{id}', [WorkflowController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/{id}', [WorkflowController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [WorkflowController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/{id}/toggle', [WorkflowController::class, 'toggle'])->where('id', '[0-9]+');
        Route::get('/{id}/executions', [WorkflowController::class, 'executions'])->where('id', '[0-9]+');
    });

    // ─── Phase-39 Chain Engine: CRM→Sales→Manufacturing ───────────────────────

    Route::prefix('workflow')->group(function () {
        // Definitions
        Route::get('/definitions',         [WorkflowChainController::class, 'indexDefinitions']);
        Route::post('/definitions',        [WorkflowChainController::class, 'storeDefinition']);
        Route::get('/definitions/{id}',    [WorkflowChainController::class, 'showDefinition'])->where('id', '[0-9]+');
        Route::put('/definitions/{id}',    [WorkflowChainController::class, 'updateDefinition'])->where('id', '[0-9]+');
        Route::delete('/definitions/{id}', [WorkflowChainController::class, 'destroyDefinition'])->where('id', '[0-9]+');
        Route::get('/definitions/{id}/executions', [WorkflowChainController::class, 'definitionExecutions'])->where('id', '[0-9]+');

        // Executions
        Route::get('/executions',      [WorkflowChainController::class, 'indexExecutions']);
        Route::get('/executions/{id}', [WorkflowChainController::class, 'showExecution'])->where('id', '[0-9]+');

        // Manual trigger (for testing)
        Route::post('/trigger', [WorkflowChainController::class, 'manualTrigger']);

        // Stats
        Route::get('/stats', [WorkflowChainController::class, 'stats']);
    });

    // ─── GAP #25 — Flow versioning + rollback ─────────────────────────────────

    Route::prefix('flows/{id}')->where(['id' => '[0-9]+'])->group(function () {
        Route::get('/versions',                          [FlowVersionController::class, 'index']);
        Route::post('/versions',                         [FlowVersionController::class, 'store']);
        Route::post('/versions/{versionId}/restore',     [FlowVersionController::class, 'restore'])
            ->where('versionId', '[0-9]+');
    });

    // ─── GAP #23 — Sandboxed expression/code node ─────────────────────────────

    Route::prefix('workflow/code-node')->group(function () {
        Route::post('/validate',  [CodeNodeController::class, 'validate']);
        Route::post('/execute',   [CodeNodeController::class, 'execute']);
    });

    // ─── Phase-39 HR→Payroll Chain: WorkflowDefinitionController ─────────────

    Route::prefix('workflow-chain')->group(function () {
        // Definitions CRUD + lifecycle
        Route::get('/definitions',                           [WorkflowDefinitionController::class, 'index']);
        Route::post('/definitions',                          [WorkflowDefinitionController::class, 'store']);
        Route::get('/definitions/{definition}',              [WorkflowDefinitionController::class, 'show']);
        Route::put('/definitions/{definition}',              [WorkflowDefinitionController::class, 'update']);
        Route::delete('/definitions/{definition}',           [WorkflowDefinitionController::class, 'destroy']);
        Route::put('/definitions/{definition}/toggle',       [WorkflowDefinitionController::class, 'toggle']);
        Route::post('/definitions/{definition}/test',        [WorkflowDefinitionController::class, 'test']);
        Route::get('/definitions/{definition}/executions',   [WorkflowDefinitionController::class, 'executions']);

        // Executions
        Route::get('/executions',               [WorkflowExecutionController::class, 'index']);
        Route::get('/executions/{execution}',   [WorkflowExecutionController::class, 'show']);
        Route::post('/executions/{execution}/retry', [WorkflowExecutionController::class, 'retry']);
    });

    // Chantier 8.6: the "Legacy Workflow Engine / Builder / Task / Approval
    // Routes" block that used to live here (~34 routes) was deleted — a
    // fully dead parallel subsystem, the same pattern as CRM's
    // TerritoryManagementController / Logistics' wh_*/lgx_* / Achats'
    // PurchaseApprovalChainService found elsewhere this session. 13 of its
    // 14 distinct WorkflowController@* action names (create/getCanvas/
    // delete/duplicate/publish/execute/addStep/updateStep/removeStep/
    // addTrigger/removeTrigger/validate/export/import) do not exist on the
    // real WorkflowController at all (its real methods are index/store/
    // show/update/destroy/toggle/executions/trigger/dslParse/dslValidate/
    // dslCreate/schema/actions/triggers — only `show` coincidentally
    // overlapped in name). ExecutionController/TaskController/
    // ApprovalController don't exist anywhere in Modules\Workflow at all
    // (confirmed via `grep -rn "class ExecutionController\|class
    // TaskController\|class ApprovalController"` across the whole repo —
    // TaskController/ApprovalController only exist under Projects/Core,
    // unrelated namespaces the string-based route action could never
    // resolve to). Every one of these routes was a guaranteed fatal
    // "action does not exist" error on the first hit. Task management and
    // approval workflows are already real, live features in
    // Modules/Projects and Modules/Validation respectively.
});

// Chantier 19 Lot 3: AutomationFlowController (n8n-like flow/node CRUD +
// execution + webhook trigger, its own docblock lists the full intended
// route set), WorkflowScheduleController (cron-based flow scheduling via
// FlowSchedulerService), and WorkflowTemplateController (template CRUD +
// apply-to-flow) are all real, fully-written, and back real, live models
// (AutomationFlow/AutomationNode/AutomationFlowTemplate, used by the tested
// FlowExecutionEngine) — but none has ever had a route registered anywhere
// in this file, and none has a test or a real Vue consumer
// (AIWorkflowBuilder/Index.vue and RPA/Index.vue, the two pages that would
// naturally call this, are both already documented as 100% mock with zero
// fetch calls). A real fatal class-not-found bug in
// WorkflowScheduleController/WorkflowTemplateController and a header-based
// tenant IDOR in AutomationFlowController (plus a phantom-tenant_id bug in
// WorkflowTemplateController::apply()) were all fixed in place (see each
// controller's own docblock) since they're landmines regardless of routing
// status, but
// wiring these up into full routes + RBAC + a real Vue builder UI is a
// genuinely separate, feature-sized effort (matching the scale of the BI
// 5-orphan-subsystem build-out, not a routing fix) — left as a documented
// gap rather than attempted half-built under this pass's re-verification
// scope.

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/workflow')->group(function () {
    Route::post('ai/assist', [\Modules\Workflow\Http\Controllers\Api\WorkflowAiAssistController::class, 'assist'])
        ->name('workflow.ai.assist');
});
