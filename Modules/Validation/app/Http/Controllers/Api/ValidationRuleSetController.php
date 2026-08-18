<?php

declare(strict_types=1);

namespace Modules\Validation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Validation\Models\ValidationRule;
use Modules\Validation\Models\ValidationRuleSet;
use Modules\Validation\Services\ValidationEngine;

/**
 * Chantier 8.5sv: ValidationEngine::createRuleSet()/validateWithRuleSet()
 * were real, tested (Chantier5) methods with no controller/route anywhere —
 * ValidationRuleController only ever exposed single-rule CRUD + validating
 * a payload against every persisted rule, never the rule-set grouping.
 */
class ValidationRuleSetController extends Controller
{
    public function __construct(private readonly ValidationEngine $engine)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            ValidationRuleSet::withCount('rules')->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $ruleSet = $this->engine->createRuleSet($validated['name'], $validated['description'] ?? null);

        return response()->json(['data' => $ruleSet], 201);
    }

    public function show(ValidationRuleSet $ruleSet): JsonResponse
    {
        return response()->json(['data' => $ruleSet->load('rules')]);
    }

    public function addRule(Request $request, ValidationRuleSet $ruleSet): JsonResponse
    {
        $validated = $request->validate([
            'rule_id' => 'required|integer|exists:validation_rules,id',
        ]);

        $rule = ValidationRule::findOrFail($validated['rule_id']);
        $ruleSet->addRule($rule);

        return response()->json(['data' => $ruleSet->load('rules')]);
    }

    /**
     * Validate an arbitrary payload against every rule in the set.
     */
    public function validateData(Request $request, ValidationRuleSet $ruleSet): JsonResponse
    {
        $result = $this->engine->validateWithRuleSet($request->all(), $ruleSet);

        return response()->json([
            'valid' => $result->passes(),
            'errors' => $result->errors(),
        ]);
    }
}
