<?php

namespace Modules\Validation\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Validation\Models\ApprovalRule;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Services\ApprovalWorkflowService;

/**
 * @group Controllers - Approval Rule
 *
 * Manage Approval Rule resources.
 */
class ApprovalRuleController extends Controller
{
    public function __construct(protected ApprovalWorkflowService $service) {}

    public function index(ApprovalWorkflow $workflow)
    {
        return $workflow->rules()->orderBy('rule_order')->get();
    }

    public function store(Request $request, ApprovalWorkflow $workflow)
    {
        $data = $request->validate([
            'condition_type' => ['required', 'string', Rule::in(ApprovalRule::CONDITION_TYPES)],
            'condition_operator' => ['nullable', 'string', Rule::in(ApprovalRule::OPERATORS)],
            'condition_value' => 'nullable|string',
            'condition_field' => 'nullable|string',
            'required_approvers_count' => 'required|integer|min:1',
            'approval_mode' => 'required|in:sequential,parallel',
        ]);

        $data['workflow_id'] = $workflow->id;
        $data['rule_order'] = ApprovalRule::where('workflow_id', $workflow->id)->max('rule_order') + 1;
        $data['status'] = 'active';

        $rule = ApprovalRule::create($data);

        return response()->json($rule, 201);
    }

    public function show(ApprovalWorkflow $workflow, ApprovalRule $rule)
    {
        return $rule;
    }

    public function update(Request $request, ApprovalWorkflow $workflow, ApprovalRule $rule)
    {
        $data = $request->validate([
            'condition_type' => ['string', Rule::in(ApprovalRule::CONDITION_TYPES)],
            'condition_operator' => ['nullable', 'string', Rule::in(ApprovalRule::OPERATORS)],
            'condition_value' => 'nullable|string',
            'condition_field' => 'nullable|string',
            'required_approvers_count' => 'integer|min:1',
            'approval_mode' => 'in:sequential,parallel',
            'status' => 'in:active,inactive',
        ]);

        $rule->update($data);

        return $rule;
    }

    public function destroy(ApprovalWorkflow $workflow, ApprovalRule $rule)
    {
        $rule->delete();

        return response()->noContent();
    }
}
