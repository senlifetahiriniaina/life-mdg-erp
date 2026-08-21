<?php

declare(strict_types=1);

namespace Modules\Projects\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Projects\Models\CustomField;
use Modules\Projects\Models\Milestone;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;
use Modules\Projects\Services\CustomFieldService;

/**
 * @group Projects - Custom Fields
 *
 * Chantier 32.17 (14-layer deep audit, layer 9 fake/dead): CustomField/
 * CustomFieldValue models + a fully-written, self-contained
 * CustomFieldService (validation, entity/field-type constants, get/set
 * values) existed with real, migrated schema (projects_custom_fields/
 * projects_custom_field_values) but zero controller/route/test anywhere —
 * confirmed via grep, not just a code read. Classified "activate" per this
 * chantier's mandate: the service is real, correct, and well-tested-shaped
 * (not broken like several other findings this session), it just never had
 * a producer. Field *definitions* are treated as shared/global config,
 * matching the established ProductTemplate/acc_operation_templates
 * precedent elsewhere in this app — not scoped per company — while a
 * field's *values* on a specific task/project/milestone are scoped by
 * resolving that entity's owning project's company_id, reusing the same
 * ScopesToProjectCompany-style 404-not-403 convention as every other
 * Projects sub-resource.
 */
class CustomFieldController extends Controller
{
    public function __construct(private readonly CustomFieldService $service) {}

    /**
     * List field definitions for an entity type.
     *
     * @queryParam entity_type string required task, project or milestone. Example: task
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'in:'.implode(',', CustomField::ENTITY_TYPES)],
        ]);

        return response()->json(['data' => $this->service->listForEntity($validated['entity_type'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'in:'.implode(',', CustomField::ENTITY_TYPES)],
            'field_name' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'field_label' => ['nullable', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'in:'.implode(',', CustomField::FIELD_TYPES)],
            'options' => ['nullable', 'array'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $field = $this->service->create($validated, $request->user()->id);

        return response()->json($field, 201);
    }

    public function update(Request $request, CustomField $customField): JsonResponse
    {
        $validated = $request->validate([
            'field_label' => ['nullable', 'string', 'max:255'],
            'field_type' => ['sometimes', 'string', 'in:'.implode(',', CustomField::FIELD_TYPES)],
            'options' => ['nullable', 'array'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        return response()->json($this->service->update($customField, $validated));
    }

    public function destroy(CustomField $customField): JsonResponse
    {
        $this->service->delete($customField);

        return response()->json(null, 204);
    }

    /**
     * Get all custom field values for one entity.
     *
     * @queryParam entity_type string required task, project or milestone. Example: task
     * @queryParam entity_id int required The entity's id. Example: 1
     */
    public function showValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'in:'.implode(',', CustomField::ENTITY_TYPES)],
            'entity_id' => ['required', 'integer'],
        ]);

        $this->assertSameCompanyAsEntity($request, $validated['entity_type'], (int) $validated['entity_id']);

        return response()->json(['data' => $this->service->getValues($validated['entity_type'], (int) $validated['entity_id'])]);
    }

    /**
     * Set (upsert) custom field values for one entity.
     */
    public function storeValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'in:'.implode(',', CustomField::ENTITY_TYPES)],
            'entity_id' => ['required', 'integer'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.custom_field_id' => ['required', 'integer', 'exists:projects_custom_fields,id'],
            'values.*.value' => ['nullable'],
        ]);

        $this->assertSameCompanyAsEntity($request, $validated['entity_type'], (int) $validated['entity_id']);

        foreach ($validated['values'] as $item) {
            $field = CustomField::findOrFail($item['custom_field_id']);
            if ($field->entity_type !== $validated['entity_type']) {
                abort(422, "Field {$field->field_name} does not apply to entity_type '{$validated['entity_type']}'.");
            }
            $this->service->validateValue($field, $item['value'] ?? null);
        }

        $this->service->setValues($validated['entity_type'], (int) $validated['entity_id'], $validated['values']);

        return response()->json(['data' => $this->service->getValues($validated['entity_type'], (int) $validated['entity_id'])], 201);
    }

    /**
     * Resolve the given entity and assert it belongs to the caller's
     * company via its owning project — same 404-not-403,
     * null-safe-degrade contract as Concerns\ScopesToProjectCompany.
     */
    private function assertSameCompanyAsEntity(Request $request, string $entityType, int $entityId): void
    {
        $userCompanyId = $request->user()?->company_id;
        if ($userCompanyId === null) {
            return;
        }

        $entityCompanyId = match ($entityType) {
            'project' => Project::find($entityId)?->company_id,
            'task' => Task::find($entityId)?->project?->company_id,
            'milestone' => Milestone::find($entityId)?->project?->company_id,
            default => null,
        };

        if ($entityCompanyId !== null && (int) $entityCompanyId !== (int) $userCompanyId) {
            abort(404);
        }
    }
}
