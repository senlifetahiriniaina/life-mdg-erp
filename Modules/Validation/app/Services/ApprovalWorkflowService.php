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
        //
        // Chantier 8.5sv: replicate(array $except) takes attribute NAMES to
        // exclude, not a key=>value map of overrides — passing
        // ['workflow_id' => $newWorkflow->id] was a no-op for exclusion
        // purposes (Arr::except iterates the array's VALUES as keys to
        // remove, so it tried to remove an attribute literally named
        // $newWorkflow->id, which doesn't exist) and did NOT set the new
        // workflow_id, so every cloned rule kept pointing at the original
        // workflow instead of the new one.
        foreach ($workflow->rules as $rule) {
            $newRule = $rule->replicate();
            $newRule->workflow_id = $newWorkflow->id;
            $newRule->save();
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
