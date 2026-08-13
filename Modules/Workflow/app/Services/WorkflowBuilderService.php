<?php

namespace Modules\Workflow\Services;

use Illuminate\Support\Facades\Cache;

class WorkflowBuilderService
{
    const CACHE_TTL = 86400;
    const STEP_TYPES = ['action', 'decision', 'parallel', 'wait', 'approval', 'notification'];
    const TRIGGER_TYPES = ['manual', 'automatic', 'scheduled', 'event_based'];

    /**
     * Get workflow builder canvas (visual representation)
     */
    public function getCanvas(string $workflowId): ?array
    {
        $workflow = Cache::get("workflow:{$workflowId}");

        if (!$workflow) {
            return null;
        }

        // Convert workflow to canvas format for visual builder
        return [
            'workflow_id' => $workflowId,
            'name' => $workflow['name'],
            'description' => $workflow['description'] ?? '',
            'status' => $workflow['status'],
            'nodes' => $this->stepsToNodes($workflow['steps'] ?? []),
            'edges' => $this->buildEdges($workflow['steps'] ?? []),
            'canvas_config' => [
                'grid_size' => 10,
                'snap_to_grid' => true,
                'auto_layout' => false,
            ],
        ];
    }

    /**
     * Convert steps to canvas nodes
     */
    private function stepsToNodes(array $steps): array
    {
        $nodes = [];

        // Add start node
        $nodes[] = [
            'id' => 'start',
            'type' => 'start',
            'label' => 'Start',
            'position' => ['x' => 0, 'y' => 0],
        ];

        // Add step nodes
        foreach ($steps as $index => $step) {
            $nodes[] = [
                'id' => $step['id'],
                'type' => $step['type'],
                'label' => $step['name'],
                'position' => ['x' => 0, 'y' => ($index + 1) * 100],
                'config' => $step['config'] ?? [],
                'timeout' => $step['timeout'] ?? 300,
                'retry_count' => $step['retry_count'] ?? 3,
            ];
        }

        // Add end node
        $nodes[] = [
            'id' => 'end',
            'type' => 'end',
            'label' => 'End',
            'position' => ['x' => 0, 'y' => (count($steps) + 1) * 100],
        ];

        return $nodes;
    }

    /**
     * Build edges between nodes
     */
    private function buildEdges(array $steps): array
    {
        $edges = [];

        // Connect start to first step
        if (!empty($steps)) {
            $edges[] = [
                'id' => 'start_' . $steps[0]['id'],
                'source' => 'start',
                'target' => $steps[0]['id'],
                'label' => 'proceed',
            ];
        } else {
            // No steps, connect start to end
            $edges[] = [
                'id' => 'start_end',
                'source' => 'start',
                'target' => 'end',
            ];

            return $edges;
        }

        // Connect steps in sequence
        for ($i = 0; $i < count($steps) - 1; $i++) {
            $edges[] = [
                'id' => $steps[$i]['id'] . '_' . $steps[$i + 1]['id'],
                'source' => $steps[$i]['id'],
                'target' => $steps[$i + 1]['id'],
                'label' => 'success',
            ];

            // Add error path if not skip_on_error
            if (!$steps[$i]['skip_on_error']) {
                $edges[] = [
                    'id' => $steps[$i]['id'] . '_error',
                    'source' => $steps[$i]['id'],
                    'target' => 'end',
                    'label' => 'error',
                    'style' => ['stroke' => 'red'],
                ];
            }
        }

        // Connect last step to end
        $lastStep = end($steps);
        $edges[] = [
            'id' => $lastStep['id'] . '_end',
            'source' => $lastStep['id'],
            'target' => 'end',
            'label' => 'complete',
        ];

        return $edges;
    }

    /**
     * Update node on canvas
     */
    public function updateNode(string $workflowId, string $nodeId, array $updates): array
    {
        $workflow = Cache::get("workflow:{$workflowId}");

        if (!$workflow) {
            return ['error' => 'Workflow not found'];
        }

        $stepIndex = $this->findStepIndex($workflow['steps'], $nodeId);

        if ($stepIndex === -1) {
            return ['error' => 'Node not found'];
        }

        $step = &$workflow['steps'][$stepIndex];

        if (isset($updates['name'])) {
            $step['name'] = $updates['name'];
        }

        if (isset($updates['config'])) {
            $step['config'] = $updates['config'];
        }

        if (isset($updates['timeout'])) {
            $step['timeout'] = $updates['timeout'];
        }

        if (isset($updates['retry_count'])) {
            $step['retry_count'] = $updates['retry_count'];
        }

        if (isset($updates['skip_on_error'])) {
            $step['skip_on_error'] = $updates['skip_on_error'];
        }

        $workflow['updated_at'] = now()->toIso8601String();
        Cache::put("workflow:{$workflowId}", $workflow, now()->addDays(365));

        return [
            'workflow_id' => $workflowId,
            'node_id' => $nodeId,
            'status' => 'updated',
        ];
    }

    /**
     * Add trigger to workflow
     */
    public function addTrigger(string $workflowId, string $type, array $config): array
    {
        $workflow = Cache::get("workflow:{$workflowId}");

        if (!$workflow) {
            return ['error' => 'Workflow not found'];
        }

        if (!in_array($type, self::TRIGGER_TYPES)) {
            return ['error' => 'Invalid trigger type'];
        }

        $trigger = [
            'id' => uniqid('trigger_'),
            'type' => $type,
            'config' => $config,
            'enabled' => true,
            'created_at' => now()->toIso8601String(),
        ];

        $workflow['triggers'][] = $trigger;
        $workflow['updated_at'] = now()->toIso8601String();

        Cache::put("workflow:{$workflowId}", $workflow, now()->addDays(365));

        return [
            'workflow_id' => $workflowId,
            'trigger_id' => $trigger['id'],
            'status' => 'added',
        ];
    }

    /**
     * Remove trigger from workflow
     */
    public function removeTrigger(string $workflowId, string $triggerId): array
    {
        $workflow = Cache::get("workflow:{$workflowId}");

        if (!$workflow) {
            return ['error' => 'Workflow not found'];
        }

        $workflow['triggers'] = array_filter(
            $workflow['triggers'],
            fn($t) => $t['id'] !== $triggerId
        );

        $workflow['updated_at'] = now()->toIso8601String();
        Cache::put("workflow:{$workflowId}", $workflow, now()->addDays(365));

        return [
            'workflow_id' => $workflowId,
            'trigger_id' => $triggerId,
            'status' => 'removed',
        ];
    }

    /**
     * Validate workflow
     */
    public function validateWorkflow(string $workflowId): array
    {
        $workflow = Cache::get("workflow:{$workflowId}");

        if (!$workflow) {
            return ['error' => 'Workflow not found'];
        }

        $errors = [];
        $warnings = [];

        // Check if workflow has steps
        if (empty($workflow['steps'])) {
            $errors[] = 'Workflow must have at least one step';
        }

        // Check all steps have valid types
        foreach ($workflow['steps'] as $step) {
            if (!in_array($step['type'], self::STEP_TYPES)) {
                $errors[] = "Invalid step type: {$step['type']}";
            }

            // Check decision steps have conditions
            if ($step['type'] === 'decision' && empty($step['config']['condition'])) {
                $errors[] = "Decision step '{$step['name']}' must have a condition";
            }

            // Check approval steps have approvers
            if ($step['type'] === 'approval' && empty($step['config']['approvers'])) {
                $warnings[] = "Approval step '{$step['name']}' has no approvers configured";
            }
        }

        // Check for dangling steps
        foreach ($workflow['steps'] as $step) {
            if (empty($step['next_steps']) && $step !== end($workflow['steps'])) {
                $warnings[] = "Step '{$step['name']}' has no outgoing connections";
            }
        }

        return [
            'workflow_id' => $workflowId,
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Export workflow as JSON
     */
    public function exportWorkflow(string $workflowId): array
    {
        $workflow = Cache::get("workflow:{$workflowId}");

        if (!$workflow) {
            return ['error' => 'Workflow not found'];
        }

        $json = json_encode($workflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            'workflow_id' => $workflowId,
            'format' => 'json',
            'data' => $json,
        ];
    }

    /**
     * Import workflow from JSON
     */
    public function importWorkflow(string $json, int $ownerId): array
    {
        $workflowData = json_decode($json, true);

        if (!$workflowData) {
            return ['error' => 'Invalid JSON format'];
        }

        $workflowId = uniqid('workflow_');

        $workflowData['id'] = $workflowId;
        $workflowData['owner_id'] = $ownerId;
        $workflowData['status'] = 'draft';
        $workflowData['created_at'] = now()->toIso8601String();
        $workflowData['updated_at'] = now()->toIso8601String();

        Cache::put("workflow:{$workflowId}", $workflowData, now()->addDays(365));

        return [
            'workflow_id' => $workflowId,
            'status' => 'imported',
        ];
    }

    /**
     * Helper: Find step index
     */
    private function findStepIndex(array $steps, string $stepId): int
    {
        foreach ($steps as $index => $step) {
            if ($step['id'] === $stepId) {
                return $index;
            }
        }

        return -1;
    }
}
