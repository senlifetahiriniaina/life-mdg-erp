<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Helpdesk\Models\EscalationRule;
use Modules\Helpdesk\Models\HelpdeskSlaPolicy;

/**
 * @group Controllers - Escalation
 *
 * Manage Escalation resources.
 */
class EscalationController extends Controller
{
    // ── SLA Policies ────────────────────────────────────────────────────────

    public function indexSla(): JsonResponse
    {
        return response()->json(HelpdeskSlaPolicy::all());
    }

    public function storeSla(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'first_response_hours' => 'required|numeric|min:0',
            'resolution_hours' => 'required|numeric|min:0',
            'business_hours_only' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $policy = HelpdeskSlaPolicy::create($validated);

        return response()->json($policy, 201);
    }

    public function showSla(HelpdeskSlaPolicy $slaPolicy): JsonResponse
    {
        return response()->json($slaPolicy);
    }

    public function updateSla(Request $request, HelpdeskSlaPolicy $slaPolicy): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'first_response_hours' => 'sometimes|required|numeric|min:0',
            'resolution_hours' => 'sometimes|required|numeric|min:0',
            'business_hours_only' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $slaPolicy->update($validated);

        return response()->json($slaPolicy);
    }

    public function destroySla(HelpdeskSlaPolicy $slaPolicy): JsonResponse
    {
        $slaPolicy->delete();

        return response()->json(['message' => 'SLA policy deleted.']);
    }

    // ── Escalation Rules ────────────────────────────────────────────────────

    public function indexRules(): JsonResponse
    {
        return response()->json(EscalationRule::orderBy('priority')->get());
    }

    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sla_policy_id' => 'nullable|integer|exists:hd_helpdesk_sla_policies,id',
            'trigger_type' => 'required|string|in:first_response_overdue,resolution_overdue,no_activity,custom',
            'trigger_hours' => 'required|numeric|min:0',
            'action_type' => 'required|string|in:change_priority,reassign,notify,add_tag',
            'action_config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        $rule = EscalationRule::create($validated);

        return response()->json($rule, 201);
    }

    public function showRule(EscalationRule $escalationRule): JsonResponse
    {
        return response()->json($escalationRule);
    }

    public function updateRule(Request $request, EscalationRule $escalationRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'trigger_type' => 'sometimes|required|string',
            'trigger_hours' => 'sometimes|required|numeric|min:0',
            'action_type' => 'sometimes|required|string',
            'action_config' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
        ]);

        $escalationRule->update($validated);

        return response()->json($escalationRule);
    }

    public function destroyRule(EscalationRule $escalationRule): JsonResponse
    {
        $escalationRule->delete();

        return response()->json(['message' => 'Escalation rule deleted.']);
    }
}
