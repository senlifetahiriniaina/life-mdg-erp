<?php

namespace Modules\Achats\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;

class ApprovalRoutingService
{
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
     * Check if a single rule applies to a PO
     */
    protected function ruleAppliesToPO(ApprovalRule $rule, PurchaseOrder $po): bool
    {
        switch ($rule->condition_type) {
            case 'amount':
                return $this->evaluateAmountCondition($po->total, $rule->condition_value);

            case 'supplier':
                return $po->supplier_id == $rule->condition_value;

            case 'supplier_category':
                // Example: could check supplier type or tier
                return true;

            case 'department':
                // Example: could check user's department
                return true;

            default:
                return true;
        }
    }

    /**
     * Evaluate numeric conditions like '> 5000' or '< 10000'
     */
    protected function evaluateAmountCondition(float $amount, string $condition): bool
    {
        // Parse conditions like "> 5000", "< 1000", ">= 500", etc.
        preg_match('/^(>=|<=|>|<|==|!=)\s*(.+)$/', trim($condition), $matches);

        if (count($matches) < 3) {
            return true;
        }

        $operator = $matches[1];
        $value = (float) $matches[2];

        return match ($operator) {
            '>' => $amount > $value,
            '<' => $amount < $value,
            '>=' => $amount >= $value,
            '<=' => $amount <= $value,
            '==' => $amount == $value,
            '!=' => $amount != $value,
            default => true,
        };
    }

    /**
     * Get list of approvers for a PO based on rules
     */
    public function getApproversForPO(PurchaseOrder $po): Collection
    {
        $workflow = $this->getApplicableWorkflow($po);

        if (! $workflow) {
            // Fallback: get admins
            return User::role('admin')->get();
        }

        // Find first matching rule
        $applicableRule = $workflow->rules()
            ->orderBy('rule_order')
            ->get()
            ->first(fn ($rule) => $this->ruleAppliesToPO($rule, $po));

        if (! $applicableRule) {
            return User::role('admin')->get();
        }

        // Get approvers based on rule count requirement
        return User::role('manager')
            ->limit($applicableRule->required_approvers_count)
            ->get();
    }

    /**
     * Create default approval workflows for common scenarios
     */
    public function createDefaultWorkflows(): void
    {
        $workflows = [
            [
                'name' => 'Standard PO Approval',
                'description' => 'Amount-based approval routing',
                'module_name' => 'Achats',
                'rules' => [
                    [
                        'rule_order' => 1,
                        'condition_type' => 'amount',
                        'condition_value' => '< 5000',
                        'required_approvers_count' => 1,
                        'approval_mode' => 'sequential',
                    ],
                    [
                        'rule_order' => 2,
                        'condition_type' => 'amount',
                        'condition_value' => '>= 5000',
                        'required_approvers_count' => 2,
                        'approval_mode' => 'sequential',
                    ],
                    [
                        'rule_order' => 3,
                        'condition_type' => 'amount',
                        'condition_value' => '>= 50000',
                        'required_approvers_count' => 3,
                        'approval_mode' => 'sequential',
                    ],
                ],
            ],
            [
                'name' => 'Emergency PO Approval',
                'description' => 'Fast-track approval for urgent orders',
                'module_name' => 'Achats',
                'rules' => [
                    [
                        'rule_order' => 1,
                        'condition_type' => 'amount',
                        'condition_value' => '< 1000',
                        'required_approvers_count' => 1,
                        'approval_mode' => 'sequential',
                    ],
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
                ApprovalRule::firstOrCreate(
                    [
                        'workflow_id' => $workflow->id,
                        'rule_order' => $ruleData['rule_order'],
                    ],
                    $ruleData
                );
            }
        }
    }
}
