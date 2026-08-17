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
}
