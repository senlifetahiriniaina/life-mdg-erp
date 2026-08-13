<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Models\CustomField;
use Modules\Core\Services\CustomFieldService;

/**
 * @group Core - Custom Fields
 *
 * Admin-managed custom field definitions and per-record value storage.
 */
class CustomFieldController extends Controller
{
    public function __construct(private readonly CustomFieldService $service)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all custom field definitions.
     * Filter by entity_type via ?entity_type=crm_contacts
     *
     * GET /api/v1/core/custom-fields
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomField::query()->orderBy('sort_order');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return response()->json($query->paginate(50));
    }

    /**
     * Create a new custom field definition (admin).
     *
     * POST /api/v1/core/custom-fields
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'max:100'],
            'field_key' => ['required', 'string', 'max:100', 'alpha_dash'],
            'field_label' => ['required', 'string', 'max:255'],
            'field_type' => ['sometimes', 'string', 'in:text,textarea,number,decimal,boolean,date,datetime,select,multi_select,url,email,phone'],
            'is_required' => ['sometimes', 'boolean'],
            'is_unique' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'default_value' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'validation_rules' => ['nullable', 'string'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $field = CustomField::create($validated);

        return response()->json($field, 201);
    }

    /**
     * Show a single custom field definition.
     *
     * GET /api/v1/core/custom-fields/{field}
     */
    public function show(CustomField $field): JsonResponse
    {
        return response()->json($field);
    }

    /**
     * Update a custom field definition (admin).
     *
     * PUT /api/v1/core/custom-fields/{field}
     */
    public function update(Request $request, CustomField $field): JsonResponse
    {
        $validated = $request->validate([
            'field_label' => ['sometimes', 'string', 'max:255'],
            'field_type' => ['sometimes', 'string', 'in:text,textarea,number,decimal,boolean,date,datetime,select,multi_select,url,email,phone'],
            'is_required' => ['sometimes', 'boolean'],
            'is_unique' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'default_value' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'validation_rules' => ['nullable', 'string'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $field->update($validated);

        return response()->json($field);
    }

    /**
     * Delete a custom field definition (admin).
     *
     * DELETE /api/v1/core/custom-fields/{field}
     */
    public function destroy(CustomField $field): JsonResponse
    {
        $field->delete();

        return response()->json(['message' => 'Custom field deleted.']);
    }

    /**
     * Get all active field definitions for an entity type, grouped by group_name.
     *
     * GET /api/v1/core/custom-fields/entity/{entityType}
     */
    public function fieldsForEntity(string $entityType): JsonResponse
    {
        $grouped = $this->service->getFieldsForEntity($entityType);

        return response()->json(['data' => $grouped]);
    }

    /**
     * Validate custom field values for a given entity type.
     *
     * POST /api/v1/core/custom-fields/validate
     */
    public function validateValues(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => ['required', 'string'],
            'values' => ['required', 'array'],
        ]);

        $result = $this->service->validateValues(
            $request->input('entity_type'),
            $request->input('values', [])
        );

        return response()->json($result);
    }

    /**
     * Get all custom field values for an entity record.
     *
     * GET /api/v1/core/entity/{entityType}/{entityId}/custom-values
     */
    public function getValues(string $entityType, int $entityId): JsonResponse
    {
        $values = $this->service->getValues($entityType, $entityId);

        return response()->json(['data' => $values]);
    }

    /**
     * Bulk save custom field values for an entity record.
     *
     * POST /api/v1/core/entity/{entityType}/{entityId}/custom-values
     */
    public function saveValues(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
        ]);

        $this->service->saveValues($entityType, $entityId, $request->input('values', []));

        $result = $this->service->getValues($entityType, $entityId);

        return response()->json(['data' => $result]);
    }

    /**
     * Search entities by custom field value.
     *
     * POST /api/v1/core/custom-fields/search
     */
    public function searchByField(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => ['required', 'string'],
            'field_key' => ['required', 'string'],
            'search_value' => ['required', 'string'],
        ]);

        $results = $this->service->searchByField(
            $request->input('entity_type'),
            $request->input('field_key'),
            $request->input('search_value')
        );

        return response()->json(['data' => $results]);
    }
}
