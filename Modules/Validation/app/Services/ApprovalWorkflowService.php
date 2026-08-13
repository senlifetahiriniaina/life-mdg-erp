<?php

namespace Modules\Validation\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;

class ApprovalWorkflowService
{
    public function createWorkflow(array $data): ApprovalWorkflow
    {
        return ApprovalWorkflow::create($data);
    }

    public function updateWorkflow(ApprovalWorkflow $workflow, array $data): ApprovalWorkflow
    {
        $workflow->update($data);

        return $workflow;
    }

    public function addRule(ApprovalWorkflow $workflow, array $ruleData): ApprovalRule
    {
        return $workflow->rules()->create($ruleData);
    }

    public function deleteRule(ApprovalRule $rule): bool
    {
        return $rule->delete();
    }

    public function getApplicableWorkflow(string $module, string $entityType): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::where('module_name', $module)
            ->where('is_active', true)
            ->first();
    }

    public function cloneWorkflow(ApprovalWorkflow $workflow, string $newName): ApprovalWorkflow
    {
        $newWorkflow = $workflow->replicate();
        $newWorkflow->name = $newName;
        $newWorkflow->save();

        // Clone all rules
        foreach ($workflow->rules as $rule) {
            $rule->replicate(['workflow_id' => $newWorkflow->id])->save();
        }

        return $newWorkflow;
    }

    public function getAllActive(): Collection
    {
        return ApprovalWorkflow::where('is_active', true)->get();
    }

    public function getByModule(string $module): Collection
    {
        return ApprovalWorkflow::where('module_name', $module)
            ->where('is_active', true)
            ->get();
    }
}
