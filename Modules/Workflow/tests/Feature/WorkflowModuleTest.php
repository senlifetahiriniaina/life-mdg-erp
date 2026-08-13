<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Workflow\Providers\WorkflowServiceProvider;
use Tests\TestCase;

use Modules\Workflow\Services\ApprovalWorkflowService;
use Modules\Workflow\Services\TaskManagementService;
use Modules\Workflow\Services\WorkflowBuilderService;
use Modules\Workflow\Services\WorkflowEngineService;

// ─── Service Provider ─────────────────────────────────────────────────────────

test('WorkflowServiceProvider class exists', function () {
    expect(class_exists(WorkflowServiceProvider::class))->toBeTrue();
});

// ─── Service classes can be instantiated ─────────────────────────────────────

test('WorkflowEngineService can be instantiated', function () {
    expect(app(WorkflowEngineService::class))->toBeInstanceOf(WorkflowEngineService::class);
});

test('WorkflowBuilderService can be instantiated', function () {
    expect(app(WorkflowBuilderService::class))->toBeInstanceOf(WorkflowBuilderService::class);
});

test('TaskManagementService can be instantiated', function () {
    expect(app(TaskManagementService::class))->toBeInstanceOf(TaskManagementService::class);
});

test('ApprovalWorkflowService can be instantiated', function () {
    expect(app(ApprovalWorkflowService::class))->toBeInstanceOf(ApprovalWorkflowService::class);
});

// ─── WorkflowEngineService — creation ────────────────────────────────────────

test('WorkflowEngineService createWorkflow returns workflow_id and created status', function () {
    // Cache::flush() causes facade root issues in module tests
    $engine = app(WorkflowEngineService::class);

    $result = $engine->createWorkflow([
        'name'     => 'Invoice Approval',
        'type'     => 'approval',
        'owner_id' => 1,
    ]);

    expect($result)->toHaveKey('workflow_id')
        ->toHaveKey('status')
        ->and($result['status'])->toBe('created');
});

test('WorkflowEngineService addStep returns step_id and added status', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $engine = app(WorkflowEngineService::class);
    $wf     = $engine->createWorkflow(['name' => 'Test', 'type' => 'task', 'owner_id' => 1]);

    $result = $engine->addStep($wf['workflow_id'], [
        'type'   => 'action',
        'name'   => 'Send Email',
        'config' => ['action_type' => 'email', 'recipients' => ['user@example.com']],
    ]);

    expect($result)->toHaveKey('step_id')
        ->toHaveKey('status')
        ->and($result['status'])->toBe('added');
});

test('WorkflowEngineService addStep returns error for non-existent workflow', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $engine = app(WorkflowEngineService::class);

    $result = $engine->addStep('non_existent_id', [
        'type' => 'action',
        'name' => 'Ghost Step',
    ]);

    expect($result)->toHaveKey('error');
});

test('WorkflowEngineService publishWorkflow changes status to published', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $engine = app(WorkflowEngineService::class);
    $wf     = $engine->createWorkflow(['name' => 'Publish Me', 'type' => 'notification', 'owner_id' => 1]);

    $engine->addStep($wf['workflow_id'], ['type' => 'action', 'name' => 'Notify']);
    $result = $engine->publishWorkflow($wf['workflow_id']);

    expect($result['status'])->toBe('published');
});

test('WorkflowEngineService getWorkflow retrieves workflow from cache', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $engine = app(WorkflowEngineService::class);
    $wf     = $engine->createWorkflow(['name' => 'Retrieve Me', 'type' => 'task', 'owner_id' => 1]);

    $retrieved = $engine->getWorkflow($wf['workflow_id']);

    expect($retrieved)->not->toBeNull()
        ->and($retrieved['name'])->toBe('Retrieve Me');
});

test('WorkflowEngineService getWorkflow returns null for unknown id', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $engine = app(WorkflowEngineService::class);

    expect($engine->getWorkflow('fake_id_123'))->toBeNull();
});

// ─── WorkflowBuilderService ───────────────────────────────────────────────────

test('WorkflowBuilderService getCanvas returns null for non-existent workflow', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $builder = app(WorkflowBuilderService::class);

    expect($builder->getCanvas('no_such_id'))->toBeNull();
});

test('WorkflowBuilderService getCanvas returns canvas structure for existing workflow', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $engine  = app(WorkflowEngineService::class);
    $builder = app(WorkflowBuilderService::class);

    $wf = $engine->createWorkflow(['name' => 'Canvas Test', 'type' => 'task', 'owner_id' => 1]);

    $canvas = $builder->getCanvas($wf['workflow_id']);

    expect($canvas)->toHaveKey('workflow_id')
        ->toHaveKey('nodes')
        ->toHaveKey('edges');
});

test('WorkflowBuilderService STEP_TYPES contains expected types', function () {
    $types = WorkflowBuilderService::STEP_TYPES;

    expect($types)->toContain('action')
        ->toContain('decision')
        ->toContain('approval');
});

// ─── TaskManagementService ────────────────────────────────────────────────────

test('TaskManagementService createTask returns task_id and created status', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $service = app(TaskManagementService::class);

    $result = $service->createTask([
        'title'      => 'Review Invoice',
        'created_by' => 1,
        'priority'   => 'high',
    ]);

    expect($result)->toHaveKey('task_id')
        ->and($result['status'])->toBe('created');
});

test('TaskManagementService createTask defaults status to open', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $service = app(TaskManagementService::class);
    $result  = $service->createTask(['title' => 'Default Status Task', 'created_by' => 1]);

    $task = Cache::get("task:{$result['task_id']}");
    expect($task['status'])->toBe('open');
});

test('TaskManagementService assignTask returns assigned_count', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $service = app(TaskManagementService::class);
    $result  = $service->createTask(['title' => 'Assign Me', 'created_by' => 1]);

    $assigned = $service->assignTask($result['task_id'], [2, 3]);

    expect($assigned)->toHaveKey('assigned_count')
        ->and($assigned['assigned_count'])->toBe(2);
});

test('TaskManagementService assignTask returns error for non-existent task', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $service = app(TaskManagementService::class);

    $result = $service->assignTask('fake_task_id', [1, 2]);

    expect($result)->toHaveKey('error');
});

// ─── ApprovalWorkflowService ──────────────────────────────────────────────────

test('ApprovalWorkflowService createApprovalWorkflow returns approval_id', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $service = app(ApprovalWorkflowService::class);

    $result = $service->createApprovalWorkflow([
        'name'    => 'Purchase Approval',
        'type'    => 'sequential',
        'subject' => 'Purchase Order #1234',
    ]);

    expect($result)->toHaveKey('approval_id')
        ->and($result['status'])->toBe('created');
});

test('ApprovalWorkflowService addApprover adds approver to workflow', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $service = app(ApprovalWorkflowService::class);

    $workflow = $service->createApprovalWorkflow([
        'name'    => 'Approver Test',
        'type'    => 'sequential',
        'subject' => 'Leave Request',
    ]);

    $result = $service->addApprover($workflow['approval_id'], 5, 'level_1');

    expect($result)->toHaveKey('approver_id')
        ->and($result['status'])->toBe('added');
});

test('ApprovalWorkflowService addApprover returns error for non-existent workflow', function () {
    // Cache::flush() disabled - Facade root issues in module tests
    $service = app(ApprovalWorkflowService::class);

    $result = $service->addApprover('non_existent', 1, 'level_1');

    expect($result)->toHaveKey('error');
});
