<?php

declare(strict_types=1);

namespace Modules\Validation\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Validation\Models\ValidationRule;
use Modules\Validation\Services\ValidationEngine;

class ValidationRuleController extends Controller
{
    public function __construct(private readonly ValidationEngine $engine)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            ValidationRule::paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'field' => 'required|string|max:255',
            'type' => 'required|string|in:'.implode(',', ValidationRule::TYPES),
            'params' => 'nullable|array',
            'message' => 'nullable|string',
        ]);

        $rule = $this->engine->createRule(
            name: $validated['name'],
            field: $validated['field'],
            type: $validated['type'],
            params: $validated['params'] ?? [],
            message: $validated['message'] ?? null,
        );

        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, ValidationRule $validationRule): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'field' => 'sometimes|string|max:255',
            'params' => 'sometimes|array',
            'message' => 'sometimes|nullable|string',
        ]);

        $validationRule->update($validated);

        return response()->json(['data' => $validationRule]);
    }

    public function destroy(ValidationRule $validationRule): JsonResponse
    {
        $validationRule->delete();

        return response()->json(null, 204);
    }

    /**
     * Validate an arbitrary payload against every persisted rule whose
     * field is present in it.
     */
    public function validateData(Request $request): JsonResponse
    {
        $data = $request->all();
        $rules = ValidationRule::whereIn('field', array_keys($data))->get();

        $result = $this->engine->validate($data, $rules);

        return response()->json([
            'valid' => $result->passes(),
            'errors' => $result->errors(),
        ]);
    }

    /**
     * Declare that this rule depends on another rule (must be evaluated
     * first). Rejected with 422 if it would introduce a dependency cycle —
     * ValidationEngine::hasCircularDependency() was real and tested but had
     * no caller anywhere in the app before this endpoint.
     */
    public function addDependency(Request $request, ValidationRule $validationRule): JsonResponse
    {
        $validated = $request->validate([
            'depends_on_rule_id' => 'required|integer|exists:validation_rules,id',
        ]);

        if ((int) $validated['depends_on_rule_id'] === $validationRule->id) {
            return response()->json(['message' => 'A rule cannot depend on itself.'], 422);
        }

        $dependsOn = ValidationRule::findOrFail($validated['depends_on_rule_id']);

        $validationRule->dependencies()->syncWithoutDetaching([$dependsOn->id]);

        if ($this->engine->hasCircularDependency([$validationRule->fresh()])) {
            $validationRule->dependencies()->detach($dependsOn->id);

            return response()->json([
                'message' => 'This dependency would introduce a circular reference.',
            ], 422);
        }

        return response()->json(['data' => $validationRule->load('dependencies')]);
    }
}
