<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Workflow\Http\Controllers\Api\WorkflowController;
use Modules\Workflow\Http\Controllers\Api\WorkflowChainController;
use Modules\Workflow\Http\Controllers\Api\WorkflowDefinitionController;
use Modules\Workflow\Http\Controllers\Api\WorkflowExecutionController;
use Modules\Workflow\Http\Controllers\Api\FlowVersionController;
use Modules\Workflow\Http\Controllers\Api\CodeNodeController;
use Modules\Workflow\Http\Controllers\Api\AiWorkflowController;
use Modules\Workflow\Http\Controllers\Api\AutomationFlowController;
use Modules\Workflow\Http\Controllers\Api\WorkflowNodeController;
use Modules\Workflow\Http\Controllers\Api\WorkflowScheduleController;
use Modules\Workflow\Http\Controllers\Api\WorkflowTemplateController;

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

    // ─── Chantier 32.11: n8n-like Automation Flows (real n8n-like engine) ─────
    //
    // AutomationFlowController/WorkflowScheduleController/
    // WorkflowTemplateController/WorkflowNodeController/AiWorkflowController
    // were all real, fully-written, backed by real live models
    // (AutomationFlow/AutomationNode/AutomationConnection/AutomationVariable/
    // AutomationExecution/AutomationFlowTemplate, used by the tested
    // FlowExecutionEngine) but had zero routes registered anywhere since
    // Chantier 19 Lot 3, which fixed real bugs in place (fatal class-not-
    // found imports, a header-based tenant IDOR) without wiring them up,
    // leaving them as a documented gap. Wired up for real now — the
    // AutomationFlowController::webhookTrigger() endpoint is registered
    // separately below, outside this auth-gated group, since it must be
    // reachable by unauthenticated external services per its own docblock.
    Route::prefix('automation')->group(function () {
        Route::get('/flows',              [AutomationFlowController::class, 'index']);
        Route::post('/flows',             [AutomationFlowController::class, 'store']);
        Route::post('/flows/from-template', [AutomationFlowController::class, 'fromTemplate']);
        Route::get('/flows/{id}',         [AutomationFlowController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/flows/{id}',         [AutomationFlowController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/flows/{id}',      [AutomationFlowController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/flows/{id}/activate',   [AutomationFlowController::class, 'activate'])->where('id', '[0-9]+');
        Route::post('/flows/{id}/deactivate', [AutomationFlowController::class, 'deactivate'])->where('id', '[0-9]+');
        Route::post('/flows/{id}/execute',    [AutomationFlowController::class, 'execute'])->where('id', '[0-9]+');
        Route::get('/flows/{id}/executions',  [AutomationFlowController::class, 'flowExecutions'])->where('id', '[0-9]+');
        Route::post('/flows/{id}/nodes',                   [AutomationFlowController::class, 'addNode'])->where('id', '[0-9]+');
        Route::put('/flows/{flowId}/nodes/{nodeId}',       [AutomationFlowController::class, 'updateNode'])->whereNumber(['flowId', 'nodeId']);
        Route::delete('/flows/{flowId}/nodes/{nodeId}',    [AutomationFlowController::class, 'removeNode'])->whereNumber(['flowId', 'nodeId']);

        Route::get('/executions',            [AutomationFlowController::class, 'indexExecutions']);
        Route::get('/executions/{id}',       [AutomationFlowController::class, 'showExecution'])->where('id', '[0-9]+');
        Route::post('/executions/{id}/retry',  [AutomationFlowController::class, 'retryExecution'])->where('id', '[0-9]+');
        Route::post('/executions/{id}/pause',  [AutomationFlowController::class, 'pauseExecution'])->where('id', '[0-9]+');
        Route::post('/executions/{id}/resume', [AutomationFlowController::class, 'resumeExecution'])->where('id', '[0-9]+');

        Route::get('/node-types', [AutomationFlowController::class, 'nodeTypes']);

        // Chantier 32.11: AutomationFlowController::templates()/fromTemplate()
        // above cover the read-only "browse builtin templates + instantiate
        // one" flow already; WorkflowTemplateController below adds full CRUD
        // management of the template catalogue (create/update/delete/preview)
        // — a genuinely distinct, non-duplicate concern (managing the
        // catalogue vs. consuming it) — routed under a non-colliding prefix
        // rather than the same /automation/templates URI both controllers'
        // read paths would otherwise fight over (the exact silent-
        // last-registration-wins landmine already documented elsewhere in
        // this app for Helpdesk's KB routes).
        Route::get('/templates', [AutomationFlowController::class, 'templates']);

        Route::prefix('template-library')->group(function () {
            Route::get('/',                [WorkflowTemplateController::class, 'index']);
            Route::post('/',               [WorkflowTemplateController::class, 'store']);
            Route::get('/{template}',      [WorkflowTemplateController::class, 'show']);
            Route::put('/{template}',      [WorkflowTemplateController::class, 'update']);
            Route::delete('/{template}',   [WorkflowTemplateController::class, 'destroy']);
            Route::post('/{template}/apply',   [WorkflowTemplateController::class, 'apply']);
            Route::get('/{template}/preview',  [WorkflowTemplateController::class, 'preview']);
        });

        Route::prefix('schedules')->group(function () {
            Route::get('/',      [WorkflowScheduleController::class, 'index']);
            Route::get('/due',   [WorkflowScheduleController::class, 'due']);
        });
        Route::prefix('flows/{flow}/schedule')->group(function () {
            Route::get('/',         [WorkflowScheduleController::class, 'show']);
            Route::put('/',         [WorkflowScheduleController::class, 'update']);
            Route::post('/enable',  [WorkflowScheduleController::class, 'enable']);
            Route::post('/disable', [WorkflowScheduleController::class, 'disable']);
        });
    });

    Route::prefix('workflow/nodes')->group(function () {
        // Static segments registered before the {key} wildcard — Laravel
        // matches routes in registration order, so 'by-module'/'by-category'/
        // 'test' would otherwise be captured as $key.
        Route::get('/by-module',   [WorkflowNodeController::class, 'byModule']);
        Route::get('/by-category', [WorkflowNodeController::class, 'byCategory']);
        Route::post('/test',       [WorkflowNodeController::class, 'test']);
        Route::get('/',            [WorkflowNodeController::class, 'index']);
        Route::get('/{key}',       [WorkflowNodeController::class, 'show'])->where('key', '.*');
    });

    Route::prefix('workflow/ai')->group(function () {
        Route::get('/suggest',            [AiWorkflowController::class, 'suggest']);
        Route::get('/analyze-failures',   [AiWorkflowController::class, 'analyzeFailures']);
        Route::post('/validate',          [AiWorkflowController::class, 'validateFlow']);
        Route::post('/generate',          [AiWorkflowController::class, 'generate']);
        Route::get('/flows/{flowId}/summary', [AiWorkflowController::class, 'summary']);
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

// Chantier 32.11: AutomationFlowController/WorkflowScheduleController/
// WorkflowTemplateController/WorkflowNodeController/AiWorkflowController —
// left as a documented gap since Chantier 19 Lot 3 ("a genuinely separate,
// feature-sized effort... left as a documented gap") — are now all routed
// for real above (inside the main role:manager,admin group). Still
// deliberately NOT wired to any Vue page: AIWorkflowBuilder/Index.vue and
// RPA/Index.vue remain 100% mock (zero fetch calls, confirmed unchanged by
// this chantier) — building a real visual n8n-like builder UI on top of
// this now-real API surface is still a separate, genuinely feature-sized
// frontend effort, out of this backend-audit chantier's scope. The one
// endpoint that must stay outside the auth-gated group above —
// AutomationFlowController::webhookTrigger(), a public inbound-webhook
// receiver by design (its own docblock: "no auth:sanctum middleware
// required") — is registered next, throttled the same way every other
// public webhook receiver in this app is (config('webhook') rate limiter).
Route::middleware(['throttle:webhook'])->prefix('v1/automation')->group(function () {
    Route::post('webhook/{uuid}', [AutomationFlowController::class, 'webhookTrigger']);
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/workflow')->group(function () {
    Route::post('ai/assist', [\Modules\Workflow\Http\Controllers\Api\WorkflowAiAssistController::class, 'assist'])
        ->name('workflow.ai.assist');
});
