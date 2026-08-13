<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Workflow\Services\ApprovalWorkflowService;
use Modules\Workflow\Services\TaskManagementService;
use Modules\Workflow\Services\WorkflowBuilderService;
use Modules\Workflow\Services\WorkflowEngineService;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function workflowEngine(): WorkflowEngineService
{
    return app(WorkflowEngineService::class);
}

function workflowBuilder(): WorkflowBuilderService
{
    return app(WorkflowBuilderService::class);
}

function taskManager(): TaskManagementService
{
    return app(TaskManagementService::class);
}

function approvalService(): ApprovalWorkflowService
{
    return app(ApprovalWorkflowService::class);
}

function createPublishedWorkflow(string $name = 'Test Workflow'): array
{
    $engine = workflowEngine();

    $wf = $engine->createWorkflow([
        'name'     => $name,
        'type'     => 'approval',
        'owner_id' => 1,
    ]);

    $engine->addStep($wf['workflow_id'], [
        'type'   => 'action',
        'name'   => 'Send Email',
        'config' => ['action_type' => 'email', 'recipients' => ['test@example.com']],
    ]);

    $engine->publishWorkflow($wf['workflow_id']);

    return $wf;
}

// ─── WorkflowEngineService ────────────────────────────────────────────────────

it('workflow engine creates draft workflow with correct structure', function () {
    $result = workflowEngine()->createWorkflow([
        'name'     => 'Invoice Approval',
        'type'     => 'approval',
        'owner_id' => 42,
    ]);

    expect($result['status'])->toBe('created')
        ->and($result)->toHaveKey('workflow_id')
        ->and($result['workflow_id'])->toStartWith('workflow_');

    $stored = Cache::get("workflow:{$result['workflow_id']}");
    expect($stored['status'])->toBe('draft')
        ->and($stored['owner_id'])->toBe(42)
        ->and($stored['type'])->toBe('approval')
        ->and($stored['steps'])->toBeEmpty();
});

it('workflow engine stores optional triggers and conditions', function () {
    $result = workflowEngine()->createWorkflow([
        'name'       => 'Triggered Workflow',
        'type'       => 'notification',
        'owner_id'   => 1,
        'triggers'   => [['type' => 'event_based', 'event' => 'order.created']],
        'conditions' => [['field' => 'amount', 'operator' => 'greater_than', 'value' => 1000]],
    ]);

    $stored = Cache::get("workflow:{$result['workflow_id']}");
    expect($stored['triggers'])->toHaveCount(1)
        ->and($stored['conditions'])->toHaveCount(1);
});

it('workflow engine cannot publish without steps', function () {
    $wf     = workflowEngine()->createWorkflow(['name' => 'Empty', 'type' => 'task', 'owner_id' => 1]);
    $result = workflowEngine()->publishWorkflow($wf['workflow_id']);

    expect($result)->toHaveKey('error')
        ->and($result['error'])->toContain('step');
});

it('workflow engine publish fails for unknown workflow', function () {
    $result = workflowEngine()->publishWorkflow('nonexistent_id');

    expect($result)->toHaveKey('error');
});

it('workflow engine addStep respects position ordering', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Multi-Step', 'type' => 'task', 'owner_id' => 1]);

    for ($i = 1; $i <= 3; $i++) {
        workflowEngine()->addStep($wf['workflow_id'], [
            'type'   => 'action',
            'name'   => "Step {$i}",
            'config' => [],
        ]);
    }

    $stored = Cache::get("workflow:{$wf['workflow_id']}");
    expect($stored['steps'])->toHaveCount(3)
        ->and($stored['steps'][0]['name'])->toBe('Step 1')
        ->and($stored['steps'][2]['name'])->toBe('Step 3');
});

it('workflow engine executes action step with email', function () {
    $wf = createPublishedWorkflow('Email Workflow');

    $result = workflowEngine()->executeWorkflow($wf['workflow_id'], []);

    expect($result['status'])->toBe('completed')
        ->and($result)->toHaveKey('execution_id');
});

it('workflow engine execution requires published status', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Draft', 'type' => 'task', 'owner_id' => 1]);

    workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'action',
        'name'   => 'Action',
        'config' => [],
    ]);

    $result = workflowEngine()->executeWorkflow($wf['workflow_id'], []);

    expect($result)->toHaveKey('error')
        ->and($result['error'])->toContain('published');
});

it('workflow engine decision step evaluates greater_than operator', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Decision Flow', 'type' => 'approval', 'owner_id' => 1]);

    workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'decision',
        'name'   => 'Check Budget',
        'config' => ['condition' => ['field' => 'budget', 'operator' => 'greater_than', 'value' => 5000]],
    ]);

    workflowEngine()->publishWorkflow($wf['workflow_id']);

    $highResult = workflowEngine()->executeWorkflow($wf['workflow_id'], ['budget' => 10000]);
    $lowResult  = workflowEngine()->executeWorkflow($wf['workflow_id'], ['budget' => 1000]);

    expect($highResult['status'])->toBe('completed')
        ->and($lowResult['status'])->toBe('completed');

    $highExec = Cache::get("execution:{$highResult['execution_id']}");
    $lowExec  = Cache::get("execution:{$lowResult['execution_id']}");

    $stepId = Cache::get("workflow:{$wf['workflow_id']}")['steps'][0]['id'];

    expect($highExec['results'][$stepId]['result'])->toBeTrue()
        ->and($lowExec['results'][$stepId]['result'])->toBeFalse();
});

it('workflow engine decision evaluates less_than operator', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Less Than Flow', 'type' => 'task', 'owner_id' => 1]);

    workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'decision',
        'name'   => 'Check Stock',
        'config' => ['condition' => ['field' => 'stock', 'operator' => 'less_than', 'value' => 10]],
    ]);

    workflowEngine()->publishWorkflow($wf['workflow_id']);

    $result = workflowEngine()->executeWorkflow($wf['workflow_id'], ['stock' => 5]);

    $stepId  = Cache::get("workflow:{$wf['workflow_id']}")['steps'][0]['id'];
    $execId  = $result['execution_id'];
    $exec    = Cache::get("execution:{$execId}");

    expect($exec['results'][$stepId]['result'])->toBeTrue();
});

it('workflow engine skip_on_error continues despite failure', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Resilient Flow', 'type' => 'task', 'owner_id' => 1]);

    // Add a step with an unsupported type that still completes
    workflowEngine()->addStep($wf['workflow_id'], [
        'type'          => 'wait',
        'name'          => 'Wait Step',
        'config'        => ['duration' => 0, 'unit' => 'seconds'],
        'skip_on_error' => true,
    ]);

    workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'action',
        'name'   => 'Final Action',
        'config' => ['action_type' => 'database'],
    ]);

    workflowEngine()->publishWorkflow($wf['workflow_id']);
    $result = workflowEngine()->executeWorkflow($wf['workflow_id'], []);

    expect($result['status'])->toBe('completed');
});

it('workflow engine getExecution returns stored execution data', function () {
    $wf     = createPublishedWorkflow();
    $result = workflowEngine()->executeWorkflow($wf['workflow_id'], ['key' => 'val']);

    $exec = workflowEngine()->getExecution($result['execution_id']);

    expect($exec)->not->toBeNull()
        ->and($exec['status'])->toBe('completed')
        ->and($exec['context']['key'])->toBe('val');
});

it('workflow engine getExecution returns null for unknown id', function () {
    expect(workflowEngine()->getExecution('unknown_exec_id'))->toBeNull();
});

it('workflow engine getWorkflow returns null after deletion', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'To Delete', 'type' => 'task', 'owner_id' => 1]);

    workflowEngine()->deleteWorkflow($wf['workflow_id']);

    expect(workflowEngine()->getWorkflow($wf['workflow_id']))->toBeNull();
});

it('workflow engine duplicate preserves steps', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Original', 'type' => 'task', 'owner_id' => 1]);

    workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'action',
        'name'   => 'Original Step',
        'config' => [],
    ]);

    $dup = workflowEngine()->duplicateWorkflow($wf['workflow_id'], 'Clone');

    $cloned = Cache::get("workflow:{$dup['new_workflow_id']}");

    expect($cloned['name'])->toBe('Clone')
        ->and($cloned['status'])->toBe('draft')
        ->and($cloned['steps'])->toHaveCount(1)
        ->and($cloned['steps'][0]['name'])->toBe('Original Step');
});

it('workflow engine notification step executes correctly', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Notify Flow', 'type' => 'notification', 'owner_id' => 1]);

    workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'notification',
        'name'   => 'Notify Team',
        'config' => ['channels' => ['email', 'slack'], 'recipients' => [1, 2, 3]],
    ]);

    workflowEngine()->publishWorkflow($wf['workflow_id']);
    $result = workflowEngine()->executeWorkflow($wf['workflow_id'], []);

    expect($result['status'])->toBe('completed');
});

// ─── WorkflowBuilderService ───────────────────────────────────────────────────

it('workflow builder canvas includes start and end nodes', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Canvas Test', 'type' => 'task', 'owner_id' => 1]);

    workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'action',
        'name'   => 'Do Something',
        'config' => [],
    ]);

    $canvas = workflowBuilder()->getCanvas($wf['workflow_id']);

    $nodeTypes = array_column($canvas['nodes'], 'type');

    expect($canvas)->toHaveKey('nodes')
        ->and($canvas)->toHaveKey('edges')
        ->and($nodeTypes)->toContain('start')
        ->and($nodeTypes)->toContain('end');
});

it('workflow builder canvas returns null for unknown workflow', function () {
    expect(workflowBuilder()->getCanvas('unknown_id'))->toBeNull();
});

it('workflow builder validates empty workflow as invalid', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Empty', 'type' => 'task', 'owner_id' => 1]);

    $result = workflowBuilder()->validateWorkflow($wf['workflow_id']);

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();
});

it('workflow builder validates workflow with valid steps as valid', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Valid Flow', 'type' => 'task', 'owner_id' => 1]);

    workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'action',
        'name'   => 'Email Action',
        'config' => ['action_type' => 'email'],
    ]);

    $result = workflowBuilder()->validateWorkflow($wf['workflow_id']);

    expect($result['valid'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});

it('workflow builder export produces json with data key', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Export Me', 'type' => 'task', 'owner_id' => 1]);

    $result = workflowBuilder()->exportWorkflow($wf['workflow_id']);

    expect($result['format'])->toBe('json')
        ->and($result)->toHaveKey('data')
        ->and(json_decode($result['data'], true)['name'])->toBe('Export Me');
});

it('workflow builder import creates new workflow from json', function () {
    $json = json_encode([
        'name'  => 'Imported',
        'type'  => 'approval',
        'steps' => [],
    ]);

    $result = workflowBuilder()->importWorkflow($json, 7);

    expect($result['status'])->toBe('imported')
        ->and($result)->toHaveKey('workflow_id');

    $stored = Cache::get("workflow:{$result['workflow_id']}");
    expect($stored['owner_id'])->toBe(7)
        ->and($stored['status'])->toBe('draft');
});

it('workflow builder import rejects invalid json', function () {
    $result = workflowBuilder()->importWorkflow('not-valid-json', 1);

    expect($result)->toHaveKey('error');
});

it('workflow builder add trigger validates trigger type', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Trigger Test', 'type' => 'task', 'owner_id' => 1]);

    $result = workflowBuilder()->addTrigger($wf['workflow_id'], 'invalid_type', []);

    expect($result)->toHaveKey('error')
        ->and($result['error'])->toContain('Invalid trigger type');
});

it('workflow builder add valid trigger stores it', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'With Trigger', 'type' => 'task', 'owner_id' => 1]);

    $result = workflowBuilder()->addTrigger($wf['workflow_id'], 'event_based', ['event' => 'order.paid']);

    expect($result['status'])->toBe('added')
        ->and($result)->toHaveKey('trigger_id');

    $stored = Cache::get("workflow:{$wf['workflow_id']}");
    expect($stored['triggers'])->toHaveCount(1)
        ->and($stored['triggers'][0]['type'])->toBe('event_based');
});

it('workflow builder remove trigger removes by id', function () {
    $wf = workflowEngine()->createWorkflow(['name' => 'Remove Trigger', 'type' => 'task', 'owner_id' => 1]);

    $added = workflowBuilder()->addTrigger($wf['workflow_id'], 'manual', []);
    workflowBuilder()->removeTrigger($wf['workflow_id'], $added['trigger_id']);

    $stored = Cache::get("workflow:{$wf['workflow_id']}");
    expect($stored['triggers'])->toBeEmpty();
});

it('workflow builder update node modifies step config', function () {
    $wf   = workflowEngine()->createWorkflow(['name' => 'Node Update', 'type' => 'task', 'owner_id' => 1]);
    $step = workflowEngine()->addStep($wf['workflow_id'], [
        'type'   => 'action',
        'name'   => 'Original',
        'config' => ['action_type' => 'email'],
    ]);

    $result = workflowBuilder()->updateNode($wf['workflow_id'], $step['step_id'], [
        'name'   => 'Updated',
        'config' => ['action_type' => 'webhook'],
    ]);

    expect($result['status'])->toBe('updated');

    $stored = Cache::get("workflow:{$wf['workflow_id']}");
    expect($stored['steps'][0]['name'])->toBe('Updated')
        ->and($stored['steps'][0]['config']['action_type'])->toBe('webhook');
});

// ─── TaskManagementService ────────────────────────────────────────────────────

it('task manager creates task with all default fields', function () {
    $result = taskManager()->createTask([
        'title'      => 'Review Contract',
        'created_by' => 5,
    ]);

    expect($result['status'])->toBe('created')
        ->and($result)->toHaveKey('task_id');

    $task = taskManager()->getTask($result['task_id']);
    expect($task['status'])->toBe('open')
        ->and($task['priority'])->toBe('medium')
        ->and($task['created_by'])->toBe(5)
        ->and($task['subtasks'])->toBeEmpty()
        ->and($task['comments'])->toBeEmpty();
});

it('task manager creates task with custom priority', function () {
    $result = taskManager()->createTask([
        'title'      => 'Urgent Task',
        'created_by' => 1,
        'priority'   => 'urgent',
    ]);

    $task = taskManager()->getTask($result['task_id']);
    expect($task['priority'])->toBe('urgent');
});

it('task manager assigns multiple users', function () {
    $task   = taskManager()->createTask(['title' => 'Team Task', 'created_by' => 1]);
    $result = taskManager()->assignTask($task['task_id'], [10, 11, 12], 'assignee');

    expect($result['assigned_count'])->toBe(3)
        ->and($result['status'])->toBe('assigned');

    $stored = taskManager()->getTask($task['task_id']);
    expect($stored['assigned_to'])->toHaveCount(3);
});

it('task manager updates status and records completed_at when completed', function () {
    $task   = taskManager()->createTask(['title' => 'Finish This', 'created_by' => 1]);
    $result = taskManager()->updateTaskStatus($task['task_id'], 'completed', 'All done');

    expect($result['new_status'])->toBe('completed');

    $stored = taskManager()->getTask($task['task_id']);
    expect($stored['status'])->toBe('completed')
        ->and($stored)->toHaveKey('completed_at')
        ->and($stored['completed_at'])->not->toBeNull();
});

it('task manager rejects invalid status', function () {
    $task   = taskManager()->createTask(['title' => 'Invalid', 'created_by' => 1]);
    $result = taskManager()->updateTaskStatus($task['task_id'], 'invalid_status');

    expect($result)->toHaveKey('error');
});

it('task manager adds comment with user_id and text', function () {
    $task   = taskManager()->createTask(['title' => 'Discuss', 'created_by' => 1]);
    $result = taskManager()->addComment($task['task_id'], 99, 'Looks great!');

    expect($result['status'])->toBe('added')
        ->and($result)->toHaveKey('comment_id');

    $stored = taskManager()->getTask($task['task_id']);
    expect($stored['comments'])->toHaveCount(1)
        ->and($stored['comments'][0]['user_id'])->toBe(99)
        ->and($stored['comments'][0]['text'])->toBe('Looks great!');
});

it('task manager creates subtask and links to parent', function () {
    $parent = taskManager()->createTask(['title' => 'Parent', 'created_by' => 1]);
    $result = taskManager()->createSubtask($parent['task_id'], [
        'title'       => 'Sub Task A',
        'assigned_to' => 5,
    ]);

    expect($result['status'])->toBe('created')
        ->and($result)->toHaveKey('subtask_id')
        ->and($result['parent_task_id'])->toBe($parent['task_id']);

    $stored = taskManager()->getTask($parent['task_id']);
    expect($stored['subtasks'])->toHaveCount(1)
        ->and($stored['subtasks'][0]['title'])->toBe('Sub Task A');
});

it('task manager update subtask status works correctly', function () {
    $parent  = taskManager()->createTask(['title' => 'Parent', 'created_by' => 1]);
    $sub     = taskManager()->createSubtask($parent['task_id'], ['title' => 'Sub', 'assigned_to' => 1]);
    $subtaskId = $sub['subtask_id'];

    $result = taskManager()->updateSubtaskStatus($parent['task_id'], $subtaskId, 'completed');

    expect($result['status'])->toBe('updated');

    $stored = taskManager()->getTask($parent['task_id']);
    expect($stored['subtasks'][0]['status'])->toBe('completed');
});

it('task manager getTask returns null after deletion', function () {
    $task = taskManager()->createTask(['title' => 'Delete Me', 'created_by' => 1]);
    taskManager()->deleteTask($task['task_id']);

    expect(taskManager()->getTask($task['task_id']))->toBeNull();
});

it('task manager status change adds comment when notes provided', function () {
    $task = taskManager()->createTask(['title' => 'Noted', 'created_by' => 1]);
    taskManager()->updateTaskStatus($task['task_id'], 'in_progress', 'Starting now');

    $stored = taskManager()->getTask($task['task_id']);
    expect($stored['comments'])->toHaveCount(1)
        ->and($stored['comments'][0]['type'])->toBe('status_change')
        ->and($stored['comments'][0]['note'])->toBe('Starting now');
});

// ─── ApprovalWorkflowService ──────────────────────────────────────────────────

it('approval service creates workflow with sequential type', function () {
    $result = approvalService()->createApprovalWorkflow([
        'name'    => 'Budget Approval',
        'type'    => 'sequential',
        'subject' => 'Q4 Budget Request',
    ]);

    expect($result['status'])->toBe('created')
        ->and($result)->toHaveKey('approval_id');

    $stored = Cache::get("approval:{$result['approval_id']}");
    expect($stored['type'])->toBe('sequential')
        ->and($stored['status'])->toBe('pending')
        ->and($stored['approvers'])->toBeEmpty();
});

it('approval service creates workflow with parallel type', function () {
    $result = approvalService()->createApprovalWorkflow([
        'name'    => 'HR Approval',
        'type'    => 'parallel',
        'subject' => 'Hire Request',
    ]);

    $stored = Cache::get("approval:{$result['approval_id']}");
    expect($stored['type'])->toBe('parallel');
});

it('approval service creates workflow with hierarchical type', function () {
    $result = approvalService()->createApprovalWorkflow([
        'name'    => 'C-Suite Approval',
        'type'    => 'hierarchical',
        'subject' => 'Major Investment',
    ]);

    $stored = Cache::get("approval:{$result['approval_id']}");
    expect($stored['type'])->toBe('hierarchical');
});

it('approval service add approver to workflow', function () {
    $wf     = approvalService()->createApprovalWorkflow(['name' => 'Test', 'type' => 'sequential', 'subject' => 'X']);
    $result = approvalService()->addApprover($wf['approval_id'], 10, 'level_1');

    expect($result['status'])->toBe('added')
        ->and($result)->toHaveKey('approver_id');

    $stored = Cache::get("approval:{$wf['approval_id']}");
    expect($stored['approvers'])->toHaveCount(1)
        ->and($stored['approvers'][0]['user_id'])->toBe(10)
        ->and($stored['approvers'][0]['level'])->toBe('level_1')
        ->and($stored['approvers'][0]['status'])->toBe('pending');
});

it('approval service submit approval sets decision', function () {
    $wf = approvalService()->createApprovalWorkflow(['name' => 'T', 'type' => 'sequential', 'subject' => 'X']);
    approvalService()->addApprover($wf['approval_id'], 5, 'level_1');

    $result = approvalService()->submitApproval($wf['approval_id'], 5, 'approved', 'Approved!');

    expect($result['decision'])->toBe('approved')
        ->and($result['workflow_status'])->toBe('approved');
});

it('approval service submit rejection sets workflow to rejected', function () {
    $wf = approvalService()->createApprovalWorkflow(['name' => 'T', 'type' => 'sequential', 'subject' => 'X']);
    approvalService()->addApprover($wf['approval_id'], 5, 'level_1');

    $result = approvalService()->submitApproval($wf['approval_id'], 5, 'rejected', 'Does not qualify');

    expect($result['decision'])->toBe('rejected')
        ->and($result['workflow_status'])->toBe('rejected');
});

it('approval service handles conditional decision', function () {
    $wf = approvalService()->createApprovalWorkflow(['name' => 'T', 'type' => 'sequential', 'subject' => 'X']);
    approvalService()->addApprover($wf['approval_id'], 5, 'level_1');
    approvalService()->addApprover($wf['approval_id'], 6, 'level_2');

    approvalService()->submitApproval($wf['approval_id'], 5, 'conditional');

    $stored = Cache::get("approval:{$wf['approval_id']}");
    expect($stored['status'])->toBe('conditional');
});

it('approval service rejects decision from non-approver', function () {
    $wf     = approvalService()->createApprovalWorkflow(['name' => 'T', 'type' => 'sequential', 'subject' => 'X']);
    $result = approvalService()->submitApproval($wf['approval_id'], 99, 'approved');

    expect($result)->toHaveKey('error');
});

it('approval service rejects invalid decision value', function () {
    $wf = approvalService()->createApprovalWorkflow(['name' => 'T', 'type' => 'sequential', 'subject' => 'X']);
    approvalService()->addApprover($wf['approval_id'], 5, 'level_1');

    $result = approvalService()->submitApproval($wf['approval_id'], 5, 'abstain');

    expect($result)->toHaveKey('error');
});

it('approval service getApprovalStats returns all keys', function () {
    approvalService()->createApprovalWorkflow(['name' => 'Stats Test', 'type' => 'sequential', 'subject' => 'X']);

    $stats = approvalService()->getApprovalStats();

    expect($stats)->toHaveKey('total')
        ->and($stats)->toHaveKey('pending')
        ->and($stats)->toHaveKey('approved')
        ->and($stats)->toHaveKey('rejected')
        ->and($stats)->toHaveKey('conditional')
        ->and($stats)->toHaveKey('average_time_to_approval');
});

it('approval service workflow stays pending when only some approved', function () {
    $wf = approvalService()->createApprovalWorkflow(['name' => 'Two-Level', 'type' => 'sequential', 'subject' => 'X']);
    approvalService()->addApprover($wf['approval_id'], 1, 'level_1');
    approvalService()->addApprover($wf['approval_id'], 2, 'level_2');

    // Only first approver approves
    approvalService()->submitApproval($wf['approval_id'], 1, 'approved');

    $stored = Cache::get("approval:{$wf['approval_id']}");
    expect($stored['status'])->toBe('pending');
});

it('approval service getApprovalWorkflow returns null for unknown id', function () {
    expect(approvalService()->getApprovalWorkflow('nonexistent_approval'))->toBeNull();
});
