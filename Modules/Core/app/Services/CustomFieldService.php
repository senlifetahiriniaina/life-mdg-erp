<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Validator;
use Modules\Core\Models\CustomField;
use Modules\Core\Models\CustomFieldValue;

class CustomFieldService
{
    /**
     * Get all active field definitions for an entity type, grouped by group_name.
     *
     * @return array<string, array<int, CustomField>>
     */
    public function getFieldsForEntity(string $entityType): array
    {
        $fields = CustomField::where('entity_type', $entityType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $grouped = [];
        foreach ($fields as $field) {
            $group = $field->group_name ?? '';
            $grouped[$group][] = $field;
        }

        return $grouped;
    }

    /**
     * Validate custom field values for a given entity type.
     *
     * @param  array<string, mixed>  $values  ['field_key' => value, ...]
     * @return array{valid: bool, errors: array<string, string>}
     */
    public function validateValues(string $entityType, array $values): array
    {
        $fields = CustomField::where('entity_type', $entityType)
            ->where('is_active', true)
            ->get()
            ->keyBy('field_key');

        $rules  = [];
        $errors = [];

        foreach ($fields as $key => $field) {
            $fieldRules = $field->getValidationRules();
            if (! empty($fieldRules)) {
                $rules[$key] = $fieldRules;
            }
        }

        // Also enforce required for fields not in $values
        foreach ($fields as $key => $field) {
            if ($field->is_required && ! array_key_exists($key, $values)) {
                $errors[$key] = "The {$field->field_label} field is required.";
            }
        }

        if (! empty($rules)) {
            $validator = Validator::make($values, $rules);
            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $key => $msgs) {
                    $errors[$key] = $msgs[0];
                }
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Bulk save custom field values for an entity record.
     *
     * @param  array<string, mixed>  $values  ['field_key' => value, ...]
     */
    public function saveValues(string $entityType, int $entityId, array $values): void
    {
        $fields = CustomField::where('entity_type', $entityType)
            ->whereIn('field_key', array_keys($values))
            ->get()
            ->keyBy('field_key');

        foreach ($values as $key => $value) {
            if (! isset($fields[$key])) {
                continue;
            }

            $field = $fields[$key];

            CustomFieldValue::updateOrCreate(
                [
                    'custom_field_id' => $field->id,
                    'entity_type'     => $entityType,
                    'entity_id'       => $entityId,
                ],
                ['value' => $value !== null ? (string) $value : null]
            );
        }
    }

    /**
     * Get all custom values for an entity record, cast to proper PHP types.
     *
     * @return array<string, mixed>
     */
    public function getValues(string $entityType, int $entityId): array
    {
        $values = CustomFieldValue::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('customField')
            ->get();

        $result = [];
        foreach ($values as $v) {
            if ($v->customField) {
                $result[$v->customField->field_key] = $v->castValue();
            }
        }

        return $result;
    }

    /**
     * Search entities by custom field value.
     *
     * @return array<int, string|null>  [entity_id => value]
     */
    public function searchByField(string $entityType, string $fieldKey, string $searchValue): array
    {
        $field = CustomField::where('entity_type', $entityType)
            ->where('field_key', $fieldKey)
            ->first();

        if (! $field) {
            return [];
        }

        $rows = CustomFieldValue::where('custom_field_id', $field->id)
            ->where('entity_type', $entityType)
            ->where('value', 'like', '%' . $searchValue . '%')
            ->get(['entity_id', 'value']);

        $result = [];
        foreach ($rows as $row) {
            $result[$row->entity_id] = $row->value;
        }

        return $result;
    }

    /**
     * Create a new custom field definition.
     *
     * @param  array<string, mixed>  $data
     */
    public function createField(string $entityType, array $data): CustomField
    {
        return CustomField::create(array_merge($data, ['entity_type' => $entityType]));
    }

    /**
     * Import custom field definitions from an array (for seeding/migration).
     *
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, CustomField>
     */
    public function importFields(array $definitions): array
    {
        $created = [];

        foreach ($definitions as $def) {
            $entityType = $def['entity_type'] ?? '';
            $fieldKey   = $def['field_key'] ?? '';

            if (! $entityType || ! $fieldKey) {
                continue;
            }

            $field = CustomField::updateOrCreate(
                ['entity_type' => $entityType, 'field_key' => $fieldKey],
                $def
            );

            $created[] = $field;
        }

        return $created;
    }
}
