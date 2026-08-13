<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\BI\Models\AlertRule;
use Modules\BI\Models\AlertCondition;
use Modules\BI\Models\AlertRecipient;
use Modules\BI\Models\AlertEscalation;
use Modules\BI\Models\AlertHistory;

/**
 * @group BI - Alerts
 *
 * Manage real-time threshold-based alerts
 */
class AlertRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AlertRule::class);

        $rules = AlertRule::with(['conditions', 'recipients', 'escalations'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($rules);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', AlertRule::class);

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'metric_source'     => 'required|string',
            'metric_source_id'  => 'required|integer',
            'is_public'         => 'boolean',
        ]);

        $rule = AlertRule::create([
            ...$validated,
            'company_id' => $request->user()->company_id,
            'created_by' => $request->user()->id,
            'status'     => 'active',
        ]);

        return response()->json($rule, 201);
    }

    public function show(AlertRule $rule): JsonResponse
    {
        $this->authorize('view', $rule);
        $rule->load(['conditions', 'recipients', 'escalations', 'dndSchedules', 'history']);

        return response()->json($rule);
    }

    public function update(Request $request, AlertRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);

        $validated = $request->validate([
            'name'          => 'string|max:255',
            'description'   => 'nullable|string',
            'status'        => 'string|in:active,inactive,paused',
            'is_public'     => 'boolean',
        ]);

        $rule->update($validated);

        return response()->json($rule);
    }

    public function destroy(AlertRule $rule): JsonResponse
    {
        $this->authorize('delete', $rule);
        $rule->delete();

        return response()->json(null, 204);
    }

    public function addCondition(Request $request, AlertRule $rule): JsonResponse
    {
        $this->authorize('manageRules', $rule);

        $validated = $request->validate([
            'condition_order'  => 'required|integer',
            'operator'         => 'required|string|in:greater_than,less_than,equals,range,contains',
            'value'            => 'required|string',
            'comparison_type'  => 'string|in:static,previous_period,rolling_average',
            'lookback_period'  => 'nullable|integer',
            'logic_operator'   => 'string|in:and,or',
        ]);

        $condition = AlertCondition::create([
            'rule_id' => $rule->id,
            ...$validated,
        ]);

        $rule->updateConditionCount();

        return response()->json($condition, 201);
    }

    public function updateCondition(Request $request, AlertRule $rule, AlertCondition $condition): JsonResponse
    {
        $this->authorize('manageRules', $rule);

        if ($condition->rule_id !== $rule->id) {
            return response()->json(['error' => 'Condition not in rule'], 404);
        }

        $validated = $request->validate([
            'operator'         => 'string|in:greater_than,less_than,equals,range,contains',
            'value'            => 'string',
            'comparison_type'  => 'string|in:static,previous_period,rolling_average',
            'lookback_period'  => 'nullable|integer',
            'logic_operator'   => 'string|in:and,or',
        ]);

        $condition->update($validated);

        return response()->json($condition);
    }

    public function deleteCondition(AlertRule $rule, AlertCondition $condition): JsonResponse
    {
        $this->authorize('manageRules', $rule);

        if ($condition->rule_id !== $rule->id) {
            return response()->json(['error' => 'Condition not in rule'], 404);
        }

        $condition->delete();
        $rule->updateConditionCount();

        return response()->json(null, 204);
    }

    public function addRecipient(Request $request, AlertRule $rule): JsonResponse
    {
        $this->authorize('manageRules', $rule);

        $validated = $request->validate([
            'recipient_type'      => 'required|string|in:user,role,group,email',
            'recipient_value'     => 'required|string',
            'notification_channel' => 'required|string|in:email,sms,slack,webhook,in_app',
        ]);

        $recipient = AlertRecipient::create([
            'rule_id' => $rule->id,
            ...$validated,
        ]);

        return response()->json($recipient, 201);
    }

    public function deleteRecipient(AlertRule $rule, AlertRecipient $recipient): JsonResponse
    {
        $this->authorize('manageRules', $rule);

        if ($recipient->rule_id !== $rule->id) {
            return response()->json(['error' => 'Recipient not in rule'], 404);
        }

        $recipient->delete();

        return response()->json(null, 204);
    }

    public function addEscalation(Request $request, AlertRule $rule): JsonResponse
    {
        $this->authorize('escalate', $rule);

        $validated = $request->validate([
            'escalation_level' => 'required|integer|min:1',
            'trigger_condition' => 'required|string|in:after_time,after_attempts',
            'trigger_value'    => 'required|integer',
            'escalation_recipients' => 'required|array',
        ]);

        $escalation = AlertEscalation::create([
            'rule_id' => $rule->id,
            ...$validated,
        ]);

        return response()->json($escalation, 201);
    }

    public function history(Request $request, AlertRule $rule): JsonResponse
    {
        $this->authorize('viewHistory', $rule);

        $history = AlertHistory::where('rule_id', $rule->id)
            ->when($request->input('status'), fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($history);
    }

    public function acknowledgeAlert(Request $request, AlertHistory $alert): JsonResponse
    {
        $rule = $alert->rule;
        $this->authorize('acknowledge', $rule);

        $validated = $request->validate([
            'acknowledgment_note' => 'nullable|string',
        ]);

        $alert->acknowledge($request->user()->id, $validated['acknowledgment_note'] ?? '');

        return response()->json(['status' => 'acknowledged']);
    }

    public function resolveAlert(AlertHistory $alert): JsonResponse
    {
        $rule = $alert->rule;
        $this->authorize('acknowledge', $rule);

        $alert->resolve();

        return response()->json(['status' => 'resolved']);
    }

    public function activate(AlertRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);
        $rule->activate();

        return response()->json(['status' => $rule->status]);
    }

    public function deactivate(AlertRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);
        $rule->deactivate();

        return response()->json(['status' => $rule->status]);
    }

    public function pause(AlertRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);
        $rule->pause();

        return response()->json(['status' => $rule->status]);
    }
}
