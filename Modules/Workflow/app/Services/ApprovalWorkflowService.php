<?php

namespace Modules\Workflow\Services;

use Illuminate\Support\Facades\Cache;

class ApprovalWorkflowService
{
    const CACHE_TTL = 86400;
    const APPROVAL_STATUSES = ['pending', 'approved', 'rejected', 'conditional'];

    /**
     * Create approval workflow
     */
    public function createApprovalWorkflow(array $config): array
    {
        $workflowId = uniqid('approval_');

        $workflow = [
            'id' => $workflowId,
            'name' => $config['name'],
            'type' => $config['type'], // sequential, parallel, hierarchical
            'subject' => $config['subject'],
            'description' => $config['description'] ?? '',
            'status' => 'pending',
            'approvers' => [],
            'approval_chain' => [],
            'rules' => $config['rules'] ?? [],
            'auto_approve_threshold' => $config['auto_approve_threshold'] ?? null,
            'timeout_days' => $config['timeout_days'] ?? 5,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put("approval:{$workflowId}", $workflow, now()->addDays(365));

        // Track IDs for enumeration without Redis
        $approvalIds = Cache::get('approval_ids', []);
        $approvalIds[] = $workflowId;
        Cache::put('approval_ids', $approvalIds, now()->addDays(365));

        return [
            'approval_id' => $workflowId,
            'status' => 'created',
        ];
    }

    /**
     * Add approver to workflow
     */
    public function addApprover(string $workflowId, int $userId, string $level, ?string $condition = null): array
    {
        $workflow = Cache::get("approval:{$workflowId}");

        if (!$workflow) {
            return ['error' => 'Approval workflow not found'];
        }

        $approverId = uniqid('approver_');

        $approver = [
            'id' => $approverId,
            'user_id' => $userId,
            'level' => $level, // level 1, 2, 3, etc.
            'condition' => $condition,
            'status' => 'pending',
            'response' => null,
            'response_time' => null,
            'comments' => [],
            'added_at' => now()->toIso8601String(),
        ];

        $workflow['approvers'][] = $approver;
        $workflow['updated_at'] = now()->toIso8601String();

        Cache::put("approval:{$workflowId}", $workflow, now()->addDays(365));

        return [
            'approval_id' => $workflowId,
            'approver_id' => $approverId,
            'status' => 'added',
        ];
    }

    /**
     * Submit approval (approve or reject)
     */
    public function submitApproval(string $workflowId, int $userId, string $decision, ?string $comments = null): array
    {
        $workflow = Cache::get("approval:{$workflowId}");

        if (!$workflow) {
            return ['error' => 'Approval workflow not found'];
        }

        $approverIndex = $this->findApproverByUser($workflow['approvers'], $userId);

        if ($approverIndex === -1) {
            return ['error' => 'User is not an approver in this workflow'];
        }

        if (!in_array($decision, ['approved', 'rejected', 'conditional'])) {
            return ['error' => 'Invalid decision'];
        }

        $approver = &$workflow['approvers'][$approverIndex];
        $approver['status'] = $decision;
        $approver['response'] = $decision;
        $approver['response_time'] = now()->toIso8601String();

        if ($comments) {
            $approver['comments'][] = [
                'text' => $comments,
                'timestamp' => now()->toIso8601String(),
            ];
        }

        $workflow['approval_chain'][] = [
            'approver_id' => $approver['id'],
            'decision' => $decision,
            'timestamp' => now()->toIso8601String(),
        ];

        // Check if all approvals are complete
        $workflowStatus = $this->determineWorkflowStatus($workflow);
        $workflow['status'] = $workflowStatus;
        $workflow['updated_at'] = now()->toIso8601String();

        Cache::put("approval:{$workflowId}", $workflow, now()->addDays(365));

        return [
            'approval_id' => $workflowId,
            'approver_id' => $approver['id'],
            'decision' => $decision,
            'workflow_status' => $workflowStatus,
        ];
    }

    /**
     * Determine overall workflow status
     */
    private function determineWorkflowStatus(array $workflow): string
    {
        $approvers = $workflow['approvers'];

        // Check if any rejections
        $hasRejection = collect($approvers)->contains(fn($a) => $a['status'] === 'rejected');

        if ($hasRejection) {
            return 'rejected';
        }

        // Check if all approved
        $allApproved = collect($approvers)->every(fn($a) => $a['status'] === 'approved');

        if ($allApproved) {
            return 'approved';
        }

        // Check if conditionally approved
        $hasConditional = collect($approvers)->contains(fn($a) => $a['status'] === 'conditional');

        if ($hasConditional) {
            return 'conditional';
        }

        // Default: still pending
        return 'pending';
    }

    /**
     * Get approval workflow
     */
    public function getApprovalWorkflow(string $workflowId): ?array
    {
        return Cache::get("approval:{$workflowId}");
    }

    /**
     * Get pending approvals for user
     */
    private function getAllApprovalKeys(): array
    {
        try {
            $redisKeys = Cache::getRedis()->keys('approval:*');
            return array_map(fn($k) => preg_replace('/^.*approval:/', 'approval:', $k), $redisKeys);
        } catch (\Throwable) {
            return array_map(fn($id) => "approval:{$id}", Cache::get('approval_ids', []));
        }
    }

    public function getPendingApprovalsForUser(int $userId): array
    {
        $keys = $this->getAllApprovalKeys();
        $pendingApprovals = [];

        foreach ($keys as $key) {
            $workflow = Cache::get($key);

            if (!$workflow) {
                continue;
            }

            $userApprover = collect($workflow['approvers'])
                ->first(fn($a) => $a['user_id'] === $userId && $a['status'] === 'pending');

            if ($userApprover) {
                $pendingApprovals[] = [
                    'approval_id' => $workflow['id'],
                    'subject' => $workflow['subject'],
                    'approver_id' => $userApprover['id'],
                    'level' => $userApprover['level'],
                    'created_at' => $workflow['created_at'],
                    'timeout_days' => $workflow['timeout_days'],
                ];
            }
        }

        return $pendingApprovals;
    }

    /**
     * Escalate approval (notify if pending too long)
     */
    public function escalateApproval(string $workflowId): array
    {
        $workflow = Cache::get("approval:{$workflowId}");

        if (!$workflow) {
            return ['error' => 'Approval workflow not found'];
        }

        $createdTime = strtotime($workflow['created_at']);
        $timeoutSeconds = $workflow['timeout_days'] * 86400;
        $daysElapsed = (time() - $createdTime) / 86400;

        if ($daysElapsed < $workflow['timeout_days']) {
            return [
                'approval_id' => $workflowId,
                'status' => 'not_escalated',
                'days_elapsed' => round($daysElapsed, 1),
                'timeout_days' => $workflow['timeout_days'],
            ];
        }

        $workflow['escalated'] = true;
        $workflow['escalated_at'] = now()->toIso8601String();
        Cache::put("approval:{$workflowId}", $workflow, now()->addDays(365));

        return [
            'approval_id' => $workflowId,
            'status' => 'escalated',
            'pending_approvers' => count(
                collect($workflow['approvers'])->filter(fn($a) => $a['status'] === 'pending')
            ),
        ];
    }

    /**
     * Get approval statistics
     */
    public function getApprovalStats(int $userId = null): array
    {
        $keys = $this->getAllApprovalKeys();

        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'conditional' => 0,
            'average_time_to_approval' => 0,
        ];

        $approvalTimes = [];

        foreach ($keys as $key) {
            $workflow = Cache::get($key);

            if (!$workflow) {
                continue;
            }

            if ($userId !== null) {
                $userApprover = collect($workflow['approvers'])
                    ->first(fn($a) => $a['user_id'] === $userId);

                if (!$userApprover) {
                    continue;
                }
            }

            $stats['total']++;

            match($workflow['status']) {
                'pending' => $stats['pending']++,
                'approved' => $stats['approved']++,
                'rejected' => $stats['rejected']++,
                'conditional' => $stats['conditional']++,
                default => null,
            };

            // Calculate average approval time
            if ($workflow['status'] !== 'pending') {
                $createdTime = strtotime($workflow['created_at']);
                $lastAction = end($workflow['approval_chain']);

                if ($lastAction) {
                    $actionTime = strtotime($lastAction['timestamp']);
                    $approvalTimes[] = ($actionTime - $createdTime) / 3600; // hours
                }
            }
        }

        if (!empty($approvalTimes)) {
            $stats['average_time_to_approval'] = round(array_sum($approvalTimes) / count($approvalTimes), 2);
        }

        return $stats;
    }

    /**
     * Helper: Find approver by user ID
     */
    private function findApproverByUser(array $approvers, int $userId): int
    {
        foreach ($approvers as $index => $approver) {
            if ($approver['user_id'] === $userId) {
                return $index;
            }
        }

        return -1;
    }
}
