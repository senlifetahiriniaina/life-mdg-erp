<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProjectsActionHandler — Phase 39
 *
 * Handles workflow actions for the Projects module (tasks, milestones, deadlines).
 */
class ProjectsActionHandler
{
    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'projects.create_task'                => $this->createTask($params, $context),
            'projects.assign_task'                => $this->assignTask($params, $context),
            'projects.update_milestone'           => $this->updateMilestone($params, $context),
            'projects.notify_deadline'            => $this->notifyDeadline($params, $context),
            'projects.create_project_from_order'  => $this->createProjectFromOrder($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Projects action: {$action}"],
        };
    }

    /**
     * action: projects.create_task
     * Create a task inside a project.
     *
     * @param  array<string,mixed>  $params   e.g. ['title' => '...', 'project_id' => 10]
     * @param  array<string,mixed>  $context
     * @return array{task_id: int|null, status: string}
     */
    public function createTask(array $params, array $context): array
    {
        $tenantId  = $context['tenant_id'] ?? 1;
        $projectId = $params['project_id'] ?? ($context['project_id'] ?? null);
        $title     = $params['title'] ?? ($context['title'] ?? 'Tâche auto-créée');
        $dueDate   = $params['due_date'] ?? null;
        $priority  = $params['priority'] ?? 'normal';

        try {
            $taskId = DB::table('project_tasks')->insertGetId([
                'tenant_id'  => $tenantId,
                'project_id' => $projectId,
                'title'      => $title,
                'due_date'   => $dueDate,
                'priority'   => $priority,
                'status'     => 'todo',
                'source'     => 'workflow_automation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('WorkflowAction: project task created', ['task_id' => $taskId]);

            return ['task_id' => $taskId, 'status' => 'created', 'project_id' => $projectId];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createTask skipped', ['error' => $e->getMessage()]);
            return ['task_id' => null, 'status' => 'simulated', 'project_id' => $projectId];
        }
    }

    /**
     * action: projects.assign_task
     * Assign a task to a team member.
     *
     * @param  array<string,mixed>  $params   e.g. ['user_id' => 12]
     * @param  array<string,mixed>  $context
     * @return array{assigned: bool, task_id: int|null}
     */
    public function assignTask(array $params, array $context): array
    {
        $taskId   = $context['task_id'] ?? null;
        $tenantId = $context['tenant_id'] ?? 1;
        $userId   = $params['user_id'] ?? ($context['user_id'] ?? null);

        if (! $taskId) {
            return ['status' => 'error', 'reason' => 'Missing task_id in context'];
        }

        try {
            $rows = DB::table('project_tasks')
                ->where('id', $taskId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'assigned_to' => $userId,
                    'assigned_at' => now(),
                    'updated_at'  => now(),
                ]);

            return ['assigned' => $rows > 0, 'task_id' => $taskId, 'user_id' => $userId];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: assignTask skipped', ['error' => $e->getMessage()]);
            return ['assigned' => false, 'task_id' => $taskId, 'status' => 'simulated'];
        }
    }

    /**
     * action: projects.update_milestone
     * Mark a project milestone as reached.
     *
     * @param  array<string,mixed>  $params   e.g. ['milestone_id' => 5, 'completion' => 100]
     * @param  array<string,mixed>  $context
     * @return array{updated: bool, milestone_id: int|null}
     */
    public function updateMilestone(array $params, array $context): array
    {
        $milestoneId = $params['milestone_id'] ?? ($context['milestone_id'] ?? null);
        $tenantId    = $context['tenant_id'] ?? 1;
        $completion  = (int) ($params['completion'] ?? 100);

        if (! $milestoneId) {
            return ['status' => 'error', 'reason' => 'Missing milestone_id'];
        }

        try {
            $rows = DB::table('project_milestones')
                ->where('id', $milestoneId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'completion'    => $completion,
                    'reached_at'    => $completion >= 100 ? now() : null,
                    'updated_at'    => now(),
                ]);

            return ['updated' => $rows > 0, 'milestone_id' => $milestoneId, 'completion' => $completion];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: updateMilestone skipped', ['error' => $e->getMessage()]);
            return ['updated' => false, 'milestone_id' => $milestoneId, 'status' => 'simulated'];
        }
    }

    /**
     * action: projects.notify_deadline
     * Send an alert notification for an approaching deadline.
     *
     * @param  array<string,mixed>  $params   e.g. ['days_before' => 3]
     * @param  array<string,mixed>  $context
     * @return array{notified: bool}
     */
    public function notifyDeadline(array $params, array $context): array
    {
        $taskId     = $context['task_id'] ?? null;
        $projectId  = $context['project_id'] ?? null;
        $tenantId   = $context['tenant_id'] ?? 1;
        $daysBefore = (int) ($params['days_before'] ?? 3);

        try {
            DB::table('notifications')->insert([
                'tenant_id'       => $tenantId,
                'notifiable_type' => 'task',
                'notifiable_id'   => (int) $taskId,
                'type'            => 'projects.deadline_alert',
                'data'            => json_encode([
                    'task_id'     => $taskId,
                    'project_id'  => $projectId,
                    'days_before' => $daysBefore,
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable) {
            // Non-blocking
        }

        return ['notified' => true, 'days_before' => $daysBefore];
    }

    /**
     * action: projects.create_project_from_order
     * Auto-create a project when a sales order is confirmed.
     *
     * @param  array<string,mixed>  $params   e.g. ['project_type' => 'delivery']
     * @param  array<string,mixed>  $context
     * @return array{project_id: int|null, status: string}
     */
    public function createProjectFromOrder(array $params, array $context): array
    {
        $orderId     = $context['order_id'] ?? null;
        $clientId    = $context['client_id'] ?? null;
        $tenantId    = $context['tenant_id'] ?? 1;
        $projectType = $params['project_type'] ?? 'delivery';
        $name        = $params['name'] ?? "Projet commande #{$orderId}";

        if (! $orderId) {
            return ['status' => 'error', 'reason' => 'Missing order_id in context'];
        }

        try {
            $projectId = DB::table('projects')->insertGetId([
                'tenant_id'    => $tenantId,
                'order_id'     => $orderId,
                'client_id'    => $clientId,
                'name'         => $name,
                'type'         => $projectType,
                'status'       => 'active',
                'source'       => 'workflow_automation',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            Log::info('WorkflowAction: project created from order', [
                'project_id' => $projectId,
                'order_id'   => $orderId,
            ]);

            return ['project_id' => $projectId, 'status' => 'created', 'order_id' => $orderId];
        } catch (\Throwable $e) {
            Log::warning('WorkflowAction: createProjectFromOrder skipped', ['error' => $e->getMessage()]);
            return ['project_id' => null, 'status' => 'simulated', 'order_id' => $orderId];
        }
    }
}
