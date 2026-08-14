<?php

namespace Modules\Achats\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Models\HierarchyLevel;
use Modules\Validation\Models\LevelApprover;
use Modules\Validation\Services\ApprovalRoutingResolver;

class ApprovalRoutingService
{
    public function __construct(protected ApprovalRoutingResolver $resolver) {}

    /**
     * Determine the appropriate approval workflow for a PO
     */
    public function getApplicableWorkflow(PurchaseOrder $po): ?ApprovalWorkflow
    {
        $po->load('supplier');

        $workflows = ApprovalWorkflow::where('module_name', 'Achats')
            ->where('is_active', true)
            ->with('rules')
            ->get();

        foreach ($workflows as $workflow) {
            if ($this->workflowAppliesToPO($workflow, $po)) {
                return $workflow;
            }
        }

        return null;
    }

    /**
     * Check if a workflow applies to a purchase order
     */
    protected function workflowAppliesToPO(ApprovalWorkflow $workflow, PurchaseOrder $po): bool
    {
        foreach ($workflow->rules as $rule) {
            if ($this->ruleAppliesToPO($rule, $po)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a single rule applies to a PO. Delegates to
     * ApprovalRule::evaluateCondition() (the shared, eval()-free evaluator)
     * instead of re-parsing condition_value itself — this used to have its
     * own regex operator parser, duplicating (and slightly diverging from)
     * the one now on the model.
     */
    protected function ruleAppliesToPO(ApprovalRule $rule, PurchaseOrder $po): bool
    {
        return $rule->evaluateCondition($po);
    }

    /**
     * Get list of approvers for a PO, leave/working-hours-aware. Delegates to
     * ApprovalRoutingResolver — this used to hardcode `User::role('manager')`
     * regardless of what the matched rule/hierarchy actually configures.
     */
    public function getApproversForPO(PurchaseOrder $po): Collection
    {
        $workflow = $this->getApplicableWorkflow($po);

        if (! $workflow) {
            return User::role('admin')->get();
        }

        $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
            ->where('approvable_id', $po->id)
            ->where('workflow_id', $workflow->id)
            ->latest()
            ->first();

        if (! $request) {
            return User::role('admin')->get();
        }

        return $this->resolver->resolveApprovers($request);
    }

    /**
     * Create default approval workflows for common scenarios. Each rule now
     * also gets a matching role-based hierarchy (escalating through
     * purchasing-manager -> manager -> admin as the tier count grows), so
     * ApprovalRoutingResolver has something real to resolve against instead
     * of falling back to a hardcoded role. condition_value/condition_operator
     * are stored split (was a single combined string like '< 5000' before).
     */
    public function createDefaultWorkflows(): void
    {
        $escalationChain = ['purchasing-manager', 'manager', 'admin'];

        $workflows = [
            [
                'name' => 'Standard PO Approval',
                'description' => 'Amount-based approval routing',
                'module_name' => 'Achats',
                'rules' => [
                    ['rule_order' => 1, 'condition_type' => 'amount', 'condition_operator' => '<', 'condition_value' => '5000', 'required_approvers_count' => 1, 'approval_mode' => 'sequential', 'levels' => 1],
                    ['rule_order' => 2, 'condition_type' => 'amount', 'condition_operator' => '>=', 'condition_value' => '5000', 'required_approvers_count' => 2, 'approval_mode' => 'sequential', 'levels' => 2],
                    ['rule_order' => 3, 'condition_type' => 'amount', 'condition_operator' => '>=', 'condition_value' => '50000', 'required_approvers_count' => 3, 'approval_mode' => 'sequential', 'levels' => 3],
                ],
            ],
            [
                'name' => 'Emergency PO Approval',
                'description' => 'Fast-track approval for urgent orders',
                'module_name' => 'Achats',
                'rules' => [
                    ['rule_order' => 1, 'condition_type' => 'amount', 'condition_operator' => '<', 'condition_value' => '1000', 'required_approvers_count' => 1, 'approval_mode' => 'sequential', 'levels' => 1],
                ],
            ],
        ];

        foreach ($workflows as $workflowData) {
            $rules = $workflowData['rules'];
            unset($workflowData['rules']);

            $workflowData['created_by'] = User::first()->id ?? 1;
            $workflowData['is_active'] = true;

            $workflow = ApprovalWorkflow::firstOrCreate(['name' => $workflowData['name']], $workflowData);

            foreach ($rules as $ruleData) {
                $levelCount = $ruleData['levels'];
                unset($ruleData['levels']);

                $hierarchy = ApprovalHierarchy::firstOrCreate(
                    ['name' => "{$workflow->name} - Tier {$ruleData['rule_order']}"],
                    [
                        'module_name' => 'Achats',
                        'is_active' => true,
                        'escalation_role' => 'admin',
                    ]
                );

                foreach (range(1, $levelCount) as $order) {
                    $level = HierarchyLevel::firstOrCreate(
                        ['hierarchy_id' => $hierarchy->id, 'level_order' => $order],
                        ['title' => "Level {$order}", 'approver_count' => 1, 'delegation_allowed' => true]
                    );

                    LevelApprover::firstOrCreate(
                        ['hierarchy_level_id' => $level->id, 'role' => $escalationChain[$order - 1] ?? 'admin'],
                        ['is_active' => true]
                    );
                }

                ApprovalRule::firstOrCreate(
                    ['workflow_id' => $workflow->id, 'rule_order' => $ruleData['rule_order']],
                    array_merge($ruleData, ['hierarchy_id' => $hierarchy->id])
                );
            }
        }
    }
}
