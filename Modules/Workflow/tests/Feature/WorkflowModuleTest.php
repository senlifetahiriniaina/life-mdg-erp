<?php

declare(strict_types=1);

use Modules\Workflow\Providers\WorkflowServiceProvider;
use Modules\Workflow\Services\Automation\FlowExecutionEngine;
use Modules\Workflow\Services\Automation\NodeTypeRegistry;
use Modules\Workflow\Services\WorkflowActionRegistry;
use Modules\Workflow\Services\WorkflowEngineService;

/*
 * Chantier 32.11: this file previously tested ApprovalWorkflowService/
 * TaskManagementService/WorkflowBuilderService and WorkflowEngineService's
 * legacy Cache-based createWorkflow()/addStep()/publishWorkflow()/
 * getWorkflow() methods — all confirmed dead (zero real callers anywhere
 * outside these tests) and deleted this chantier. Rewritten to a small
 * smoke-test file for the module's real, still-live singletons — the
 * substantive end-to-end coverage lives in Chantier32WorkflowDeepAuditTest.php
 * and the module's other real Feature test files (WorkflowApiTest.php,
 * WorkflowChainEngineTest.php, etc).
 */

// ─── Service Provider ─────────────────────────────────────────────────────────

test('WorkflowServiceProvider class exists', function () {
    expect(class_exists(WorkflowServiceProvider::class))->toBeTrue();
});

// ─── Real, live services can be instantiated ─────────────────────────────────

test('WorkflowEngineService can be instantiated', function () {
    expect(app(WorkflowEngineService::class))->toBeInstanceOf(WorkflowEngineService::class);
});

test('WorkflowActionRegistry can be instantiated (was a guaranteed fatal TypeError before Chantier 32.11)', function () {
    expect(app(WorkflowActionRegistry::class))->toBeInstanceOf(WorkflowActionRegistry::class);
});

test('NodeTypeRegistry can be instantiated and returns a non-empty catalogue', function () {
    $registry = app(NodeTypeRegistry::class);
    expect($registry)->toBeInstanceOf(NodeTypeRegistry::class);
    expect($registry->getAll())->not->toBeEmpty();
});

test('FlowExecutionEngine can be instantiated', function () {
    expect(app(FlowExecutionEngine::class))->toBeInstanceOf(FlowExecutionEngine::class);
});
