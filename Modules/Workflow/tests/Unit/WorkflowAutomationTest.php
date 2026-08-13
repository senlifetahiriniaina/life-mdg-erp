<?php

namespace Modules\Workflow\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Workflow\Services\WorkflowEngineService;
use Modules\Workflow\Services\TaskManagementService;
use Modules\Workflow\Services\ApprovalWorkflowService;
use Modules\Workflow\Services\WorkflowBuilderService;
use Tests\TestCase;


class WorkflowAutomationTest extends TestCase
{
    protected WorkflowEngineService $engine;
    protected TaskManagementService $taskManager;
    protected ApprovalWorkflowService $approvalService;
    protected WorkflowBuilderService $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(WorkflowEngineService::class);
        $this->taskManager = app(TaskManagementService::class);
        $this->approvalService = app(ApprovalWorkflowService::class);
        $this->builder = app(WorkflowBuilderService::class);

        Cache::flush();
    }

    // ======================================================================
    // Workflow Engine Tests
    // ======================================================================

    /**
     * Test creating workflow
     */
    public function test_create_workflow()
    {
        $config = [
            'name' => 'Purchase Order Approval',
            'description' => 'Automated PO approval workflow',
            'type' => 'approval',
            'owner_id' => 1,
        ];

        $result = $this->engine->createWorkflow($config);

        $this->assertEquals('created', $result['status']);
        $this->assertArrayHasKey('workflow_id', $result);
    }

    public function test_add_step_to_workflow()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Purchase Order Approval',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $step = [
            'type' => 'action',
            'name' => 'Send Email Notification',
            'config' => ['action_type' => 'email', 'recipients' => ['user@example.com']],
        ];

        $result = $this->engine->addStep($workflowId, $step);

        $this->assertEquals('added', $result['status']);
        $this->assertArrayHasKey('step_id', $result);
    }

    public function test_publish_workflow()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Purchase Order Approval',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $this->engine->addStep($workflowId, [
            'type' => 'action',
            'name' => 'Send Email',
            'config' => [],
        ]);

        $result = $this->engine->publishWorkflow($workflowId);

        $this->assertEquals('published', $result['status']);
    }

    public function test_execute_workflow()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Purchase Order Approval',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $this->engine->addStep($workflowId, [
            'type' => 'action',
            'name' => 'Process PO',
            'config' => ['action_type' => 'database'],
        ]);

        $this->engine->publishWorkflow($workflowId);

        $result = $this->engine->executeWorkflow($workflowId, ['po_id' => 123]);

        $this->assertEquals('completed', $result['status']);
        $this->assertArrayHasKey('execution_id', $result);
    }

    public function test_workflow_with_decision_step()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Conditional Approval',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $this->engine->addStep($workflowId, [
            'type' => 'decision',
            'name' => 'Check Amount',
            'config' => ['condition' => ['field' => 'amount', 'operator' => 'greater_than', 'value' => 1000]],
        ]);

        $this->engine->publishWorkflow($workflowId);

        $result = $this->engine->executeWorkflow($workflowId, ['amount' => 500]);

        $this->assertEquals('completed', $result['status']);
    }

    public function test_duplicate_workflow()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Purchase Order Approval',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $result = $this->engine->duplicateWorkflow($workflowId, 'Cloned Workflow');

        $this->assertEquals('duplicated', $result['status']);
        $this->assertNotEquals($workflowId, $result['new_workflow_id']);
    }

    /**
     * Test delete workflow
     */
    public function test_delete_workflow()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Purchase Order Approval',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $result = $this->engine->deleteWorkflow($workflowId);

        $this->assertEquals('deleted', $result['status']);
        $this->assertNull($this->engine->getWorkflow($workflowId));
    }

    // ======================================================================
    // Task Management Tests
    // ======================================================================

    /**
     * Test create task
     */
    public function test_create_task()
    {
        $config = [
            'title' => 'Review Document',
            'description' => 'Review the submitted document',
            'created_by' => 1,
            'priority' => 'high',
        ];

        $result = $this->taskManager->createTask($config);

        $this->assertEquals('created', $result['status']);
        $this->assertArrayHasKey('task_id', $result);
    }

    public function test_assign_task()
    {
        $task = $this->taskManager->createTask([
            'title' => 'Review Document',
            'created_by' => 1,
        ]);

        $taskId = $task['task_id'];

        $result = $this->taskManager->assignTask($taskId, [2, 3], 'assignee');

        $this->assertEquals('assigned', $result['status']);
        $this->assertEquals(2, $result['assigned_count']);
    }

    public function test_update_task_status()
    {
        $task = $this->taskManager->createTask([
            'title' => 'Review Document',
            'created_by' => 1,
        ]);

        $taskId = $task['task_id'];

        $result = $this->taskManager->updateTaskStatus($taskId, 'in_progress', 'Started review');

        $this->assertEquals('in_progress', $result['new_status']);
    }

    public function test_add_comment_to_task()
    {
        $task = $this->taskManager->createTask([
            'title' => 'Review Document',
            'created_by' => 1,
        ]);

        $taskId = $task['task_id'];

        $result = $this->taskManager->addComment($taskId, 2, 'Looks good, approved');

        $this->assertEquals('added', $result['status']);
        $this->assertArrayHasKey('comment_id', $result);
    }

    public function test_create_subtask()
    {
        $task = $this->taskManager->createTask([
            'title' => 'Complete Project',
            'created_by' => 1,
        ]);

        $taskId = $task['task_id'];

        $result = $this->taskManager->createSubtask($taskId, [
            'title' => 'Review Design',
            'assigned_to' => 2,
        ]);

        $this->assertEquals('created', $result['status']);
        $this->assertArrayHasKey('subtask_id', $result);
    }

    public function test_get_user_tasks()
    {
        $task = $this->taskManager->createTask([
            'title' => 'Review Document',
            'created_by' => 1,
        ]);

        $taskId = $task['task_id'];
        $this->taskManager->assignTask($taskId, [2]);

        $userTasks = $this->taskManager->getUserTasks(2);

        $this->assertGreaterThan(0, count($userTasks));
    }

    public function test_get_overdue_tasks()
    {
        $task = $this->taskManager->createTask([
            'title' => 'Review Document',
            'created_by' => 1,
            'due_date' => now()->subDay()->toIso8601String(),
        ]);

        $overdue = $this->taskManager->getOverdueTasks();

        $this->assertGreaterThan(0, count($overdue));
    }

    /**
     * Test delete task
     */
    public function test_delete_task()
    {
        $task = $this->taskManager->createTask([
            'title' => 'Review Document',
            'created_by' => 1,
        ]);

        $taskId = $task['task_id'];

        $result = $this->taskManager->deleteTask($taskId);

        $this->assertEquals('deleted', $result['status']);
        $this->assertNull($this->taskManager->getTask($taskId));
    }

    // ======================================================================
    // Approval Workflow Tests
    // ======================================================================

    /**
     * Test create approval workflow
     */
    public function test_create_approval_workflow()
    {
        $config = [
            'name' => 'PO Approval',
            'type' => 'sequential',
            'subject' => 'Purchase Order #1000',
        ];

        $result = $this->approvalService->createApprovalWorkflow($config);

        $this->assertEquals('created', $result['status']);
        $this->assertArrayHasKey('approval_id', $result);
    }

    public function test_add_approver()
    {
        $approval = $this->approvalService->createApprovalWorkflow([
            'name' => 'PO Approval',
            'type' => 'sequential',
            'subject' => 'Purchase Order #1000',
        ]);

        $approvalId = $approval['approval_id'];

        $result = $this->approvalService->addApprover($approvalId, 2, 'level_1');

        $this->assertEquals('added', $result['status']);
        $this->assertArrayHasKey('approver_id', $result);
    }

    public function test_submit_approval()
    {
        $approval = $this->approvalService->createApprovalWorkflow([
            'name' => 'PO Approval',
            'type' => 'sequential',
            'subject' => 'Purchase Order #1000',
        ]);

        $approvalId = $approval['approval_id'];

        $this->approvalService->addApprover($approvalId, 2, 'level_1');

        $result = $this->approvalService->submitApproval($approvalId, 2, 'approved', 'Approved as requested');

        $this->assertEquals('approved', $result['decision']);
    }

    public function test_reject_approval()
    {
        $approval = $this->approvalService->createApprovalWorkflow([
            'name' => 'PO Approval',
            'type' => 'sequential',
            'subject' => 'Purchase Order #1000',
        ]);

        $approvalId = $approval['approval_id'];

        $this->approvalService->addApprover($approvalId, 2, 'level_1');

        $result = $this->approvalService->submitApproval($approvalId, 2, 'rejected', 'Does not meet criteria');

        $this->assertEquals('rejected', $result['workflow_status']);
    }

    public function test_get_pending_approvals_for_user()
    {
        $approval = $this->approvalService->createApprovalWorkflow([
            'name' => 'PO Approval',
            'type' => 'sequential',
            'subject' => 'Purchase Order #1000',
        ]);

        $approvalId = $approval['approval_id'];
        $this->approvalService->addApprover($approvalId, 2, 'level_1');

        $pending = $this->approvalService->getPendingApprovalsForUser(2);

        $this->assertGreaterThan(0, count($pending));
    }

    public function test_escalate_approval()
    {
        $approval = $this->approvalService->createApprovalWorkflow([
            'name' => 'PO Approval',
            'type' => 'sequential',
            'subject' => 'Purchase Order #1000',
        ]);

        $approvalId = $approval['approval_id'];

        $result = $this->approvalService->escalateApproval($approvalId);

        $this->assertEquals('not_escalated', $result['status']);
    }

    /**
     * Test approval statistics
     */
    public function test_approval_statistics()
    {
        $approval = $this->approvalService->createApprovalWorkflow([
            'name' => 'PO Approval',
            'type' => 'sequential',
            'subject' => 'Purchase Order #1000',
        ]);

        $stats = $this->approvalService->getApprovalStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('approved', $stats);
    }

    // ======================================================================
    // Workflow Builder Tests
    // ======================================================================

    /**
     * Test get canvas
     */
    public function test_get_canvas()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Test Workflow',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $this->engine->addStep($workflowId, [
            'type' => 'action',
            'name' => 'Step 1',
            'config' => [],
        ]);

        $canvas = $this->builder->getCanvas($workflowId);

        $this->assertNotNull($canvas);
        $this->assertArrayHasKey('nodes', $canvas);
        $this->assertArrayHasKey('edges', $canvas);
    }

    public function test_validate_workflow()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Test Workflow',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $result = $this->builder->validateWorkflow($workflowId);

        $this->assertFalse($result['valid']);
        $this->assertGreaterThan(0, count($result['errors']));
    }

    public function test_export_workflow()
    {
        $workflow = $this->engine->createWorkflow([
            'name' => 'Test Workflow',
            'type' => 'approval',
            'owner_id' => 1,
        ]);

        $workflowId = $workflow['workflow_id'];

        $result = $this->builder->exportWorkflow($workflowId);

        $this->assertEquals('json', $result['format']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_import_workflow()
    {
        $workflowJson = json_encode([
            'name' => 'Imported Workflow',
            'type' => 'approval',
            'steps' => [],
        ]);

        $result = $this->builder->importWorkflow($workflowJson, 1);

        $this->assertEquals('imported', $result['status']);
        $this->assertArrayHasKey('workflow_id', $result);
    }
}
