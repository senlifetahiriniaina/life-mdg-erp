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

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1')->group(function () {

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

    // ─── Legacy Workflow Engine / Builder / Task / Approval Routes ─────────────

    Route::middleware(['module:Workflow', 'role:manager,admin'])->prefix('workflow')->group(function () {
        // Workflow Management (engine/builder)
        Route::post('workflows', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@create');
        Route::get('workflows/{workflowId}', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@show');
        Route::get('workflows/{workflowId}/canvas', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@getCanvas');
        Route::put('workflows/{workflowId}', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@update');
        Route::delete('workflows/{workflowId}', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@delete');
        Route::post('workflows/{workflowId}/duplicate', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@duplicate');
        Route::post('workflows/{workflowId}/publish', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@publish');
        Route::post('workflows/{workflowId}/execute', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@execute');

        // Workflow Steps
        Route::post('workflows/{workflowId}/steps', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@addStep');
        Route::put('workflows/{workflowId}/steps/{stepId}', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@updateStep');
        Route::delete('workflows/{workflowId}/steps/{stepId}', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@removeStep');

        // Workflow Triggers
        Route::post('workflows/{workflowId}/triggers', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@addTrigger');
        Route::delete('workflows/{workflowId}/triggers/{triggerId}', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@removeTrigger');

        // Workflow Validation & Import/Export
        Route::post('workflows/{workflowId}/validate', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@validate');
        Route::get('workflows/{workflowId}/export', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@export');
        Route::post('workflows/import', 'Modules\Workflow\Http\Controllers\Api\WorkflowController@import');

        // Execution Management
        Route::get('executions/{executionId}', 'Modules\Workflow\Http\Controllers\Api\ExecutionController@show');
        Route::get('executions/{executionId}/status', 'Modules\Workflow\Http\Controllers\Api\ExecutionController@getStatus');
        Route::post('executions/{executionId}/cancel', 'Modules\Workflow\Http\Controllers\Api\ExecutionController@cancel');
        Route::get('workflows/{workflowId}/executions', 'Modules\Workflow\Http\Controllers\Api\ExecutionController@listByWorkflow');

        // Task Management
        Route::post('tasks', 'Modules\Workflow\Http\Controllers\Api\TaskController@create');
        Route::get('tasks/{taskId}', 'Modules\Workflow\Http\Controllers\Api\TaskController@show');
        Route::put('tasks/{taskId}', 'Modules\Workflow\Http\Controllers\Api\TaskController@update');
        Route::delete('tasks/{taskId}', 'Modules\Workflow\Http\Controllers\Api\TaskController@delete');
        Route::post('tasks/{taskId}/assign', 'Modules\Workflow\Http\Controllers\Api\TaskController@assign');
        Route::post('tasks/{taskId}/status', 'Modules\Workflow\Http\Controllers\Api\TaskController@updateStatus');
        Route::post('tasks/{taskId}/comments', 'Modules\Workflow\Http\Controllers\Api\TaskController@addComment');
        Route::post('tasks/{taskId}/attachments', 'Modules\Workflow\Http\Controllers\Api\TaskController@addAttachment');

        // Task Subtasks
        Route::post('tasks/{taskId}/subtasks', 'Modules\Workflow\Http\Controllers\Api\TaskController@createSubtask');
        Route::put('tasks/{taskId}/subtasks/{subtaskId}', 'Modules\Workflow\Http\Controllers\Api\TaskController@updateSubtask');
        Route::delete('tasks/{taskId}/subtasks/{subtaskId}', 'Modules\Workflow\Http\Controllers\Api\TaskController@removeSubtask');

        // Task Queries
        Route::get('users/{userId}/tasks', 'Modules\Workflow\Http\Controllers\Api\TaskController@getUserTasks');
        Route::get('tasks/overdue', 'Modules\Workflow\Http\Controllers\Api\TaskController@getOverdue');

        // Approval Workflows
        Route::post('approvals', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@create');
        Route::get('approvals/{approvalId}', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@show');
        Route::put('approvals/{approvalId}', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@update');
        Route::delete('approvals/{approvalId}', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@delete');

        // Approval Approvers
        Route::post('approvals/{approvalId}/approvers', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@addApprover');
        Route::post('approvals/{approvalId}/submit', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@submit');
        Route::post('approvals/{approvalId}/escalate', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@escalate');

        // Approval Queries
        Route::get('users/{userId}/pending-approvals', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@getPending');
        Route::get('approvals/statistics', 'Modules\Workflow\Http\Controllers\Api\ApprovalController@getStatistics');
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/workflow')->group(function () {
    Route::post('ai/assist', [\Modules\Workflow\Http\Controllers\Api\WorkflowAiAssistController::class, 'assist'])
        ->name('workflow.ai.assist');
});
