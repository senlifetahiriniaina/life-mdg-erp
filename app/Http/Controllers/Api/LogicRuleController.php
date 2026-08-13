<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\LogicRule;
use App\Services\LogicEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogicRuleController
{
    public function __construct(private LogicEngine $logicEngine)
    {
    }

    /**
     * List all logic rules for tenant
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = LogicRule::where('tenant_id', $user->tenant_id);

        if ($request->filled('trigger')) {
            $query->where('trigger', $request->input('trigger'));
        }

        if ($request->filled('enabled')) {
            $query->where('is_enabled', filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN));
        }

        $rules = $query
            ->with('creator:id,name,email')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($rules);
    }

    /**
     * Create new logic rule
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'trigger' => 'required|string|max:100',
            'conditions' => 'required|array',
            'actions' => 'required|array|min:1',
            'is_enabled' => 'boolean',
        ]);

        $user = Auth::user();

        $rule = LogicRule::create(array_merge($validated, [
            'tenant_id' => $user->tenant_id,
            'created_by' => $user->id,
            'is_enabled' => $validated['is_enabled'] ?? true,
        ]));

        return response()->json($rule->load('creator'), 201);
    }

    /**
     * Get logic rule by ID
     */
    public function show(LogicRule $rule): JsonResponse
    {
        $this->authorize('view', $rule);

        return response()->json($rule->load('creator'));
    }

    /**
     * Update logic rule
     */
    public function update(Request $request, LogicRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'conditions' => 'sometimes|array',
            'actions' => 'sometimes|array|min:1',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $rule->update($validated);

        return response()->json($rule->fresh('creator'));
    }

    /**
     * Delete logic rule
     */
    public function destroy(LogicRule $rule): JsonResponse
    {
        $this->authorize('delete', $rule);
        $rule->delete();

        return response()->json(null, 204);
    }

    /**
     * Test rule with sample data (dry-run)
     */
    public function test(Request $request, LogicRule $rule): JsonResponse
    {
        $this->authorize('view', $rule);

        $validated = $request->validate([
            'sample_data' => 'required|array',
        ]);

        $result = $this->logicEngine->evaluate($rule, $validated['sample_data']);

        return response()->json([
            'conditions_met' => $result['conditions_met'],
            'evaluated_conditions' => $result['condition_details'],
            'actions_to_execute' => $result['actions_to_execute'],
            'preview' => $result['preview'],
        ]);
    }

    /**
     * Enable rule
     */
    public function enable(LogicRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);
        $rule->enable();

        return response()->json(['message' => 'Rule enabled', 'rule' => $rule]);
    }

    /**
     * Disable rule
     */
    public function disable(LogicRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);
        $rule->disable();

        return response()->json(['message' => 'Rule disabled', 'rule' => $rule]);
    }

    /**
     * Get execution history for rule
     */
    public function executions(LogicRule $rule, Request $request): JsonResponse
    {
        $this->authorize('view', $rule);

        $executions = $rule->executions()
            ->orderBy('executed_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json($executions);
    }
}
