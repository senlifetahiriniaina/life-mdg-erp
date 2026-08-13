<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Modules\Projects\Models\CustomField;
use Modules\Projects\Models\CustomFieldValue;

/**
 * CustomFieldService — manage custom field definitions and their values.
 */
class CustomFieldService
{
    // -------------------------------------------------------------------------
    // Field definitions
    // -------------------------------------------------------------------------

    /**
     * Return all custom fields for a given entity type, ordered by sort_order.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CustomField>
     */
    public function listForEntity(string $entityType)
    {
        $this->assertValidEntityType($entityType);

        return CustomField::forEntity($entityType)->get();
    }

    /**
     * Create a new custom field.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): CustomField
    {
        $this->assertValidEntityType($data['entity_type']);
        $this->assertValidFieldType($data['field_type']);

        return CustomField::create([
            'entity_type' => $data['entity_type'],
            'field_name'  => $data['field_name'],
            'field_label' => $data['field_label'] ?? $data['field_name'],
            'field_type'  => $data['field_type'],
            'options'     => $data['options'] ?? null,
            'is_required' => $data['is_required'] ?? false,
            'sort_order'  => $data['sort_order'] ?? 0,
            'created_by'  => $userId,
        ]);
    }

    /**
     * Update a custom field definition.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(CustomField $field, array $data): CustomField
    {
        if (isset($data['field_type'])) {
            $this->assertValidFieldType($data['field_type']);
        }

        $field->update(array_filter([
            'field_label' => $data['field_label'] ?? null,
            'field_type'  => $data['field_type'] ?? null,
            'options'     => $data['options'] ?? null,
            'is_required' => isset($data['is_required']) ? (bool) $data['is_required'] : null,
            'sort_order'  => $data['sort_order'] ?? null,
        ], fn ($v) => $v !== null));

        return $field->fresh();
    }

    /**
     * Soft-delete a custom field (and cascade-delete its values via FK).
     */
    public function delete(CustomField $field): void
    {
        $field->delete();
    }

    // -------------------------------------------------------------------------
    // Field values
    // -------------------------------------------------------------------------

    /**
     * Set (upsert) custom field values for an entity.
     *
     * @param  array<int, array{custom_field_id: int, value: mixed}>  $values
     */
    public function setValues(string $entityType, int $entityId, array $values): void
    {
        foreach ($values as $item) {
            CustomFieldValue::updateOrCreate(
                [
                    'custom_field_id' => $item['custom_field_id'],
                    'entity_type'     => $entityType,
                    'entity_id'       => $entityId,
                ],
                ['value' => $item['value']]
            );
        }
    }

    /**
     * Retrieve all custom field values for an entity, keyed by field_name.
     *
     * @return array<string, mixed>
     */
    public function getValues(string $entityType, int $entityId): array
    {
        $values = CustomFieldValue::with('field')
            ->forEntity($entityType, $entityId)
            ->get();

        $result = [];
        foreach ($values as $v) {
            $fieldName          = $v->field?->field_name ?? (string) $v->custom_field_id;
            $result[$fieldName] = $v->value;
        }

        return $result;
    }

    /**
     * Validate a raw value against the field's type constraints.
     *
     * @throws \InvalidArgumentException if validation fails
     */
    public function validateValue(CustomField $field, mixed $value): void
    {
        if ($value === null) {
            if ($field->is_required) {
                throw new \InvalidArgumentException("Field '{$field->field_name}' is required.");
            }

            return;
        }

        match ($field->field_type) {
            'number'      => is_numeric($value) ?: throw new \InvalidArgumentException(
                "Field '{$field->field_name}' must be a number."
            ),
            'date'        => $this->isDate($value) ?: throw new \InvalidArgumentException(
                "Field '{$field->field_name}' must be a valid date (Y-m-d)."
            ),
            'checkbox'    => is_bool($value) || in_array($value, [0, 1, '0', '1', true, false], true)
                ?: throw new \InvalidArgumentException("Field '{$field->field_name}' must be a boolean."),
            'select'      => in_array($value, $field->options ?? [], true)
                ?: throw new \InvalidArgumentException(
                    "Value '{$value}' is not a valid option for '{$field->field_name}'."
                ),
            'multiselect' => $this->allInOptions((array) $value, $field->options ?? [])
                ?: throw new \InvalidArgumentException(
                    "One or more values are not valid options for '{$field->field_name}'."
                ),
            default       => null,    // text — no type constraint
        };
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertValidEntityType(string $type): void
    {
        if (! in_array($type, CustomField::ENTITY_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid entity_type '{$type}'. Allowed: " . implode(', ', CustomField::ENTITY_TYPES)
            );
        }
    }

    private function assertValidFieldType(string $type): void
    {
        if (! in_array($type, CustomField::FIELD_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid field_type '{$type}'. Allowed: " . implode(', ', CustomField::FIELD_TYPES)
            );
        }
    }

    private function isDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    /** @param list<string> $values @param list<string> $options */
    private function allInOptions(array $values, array $options): bool
    {
        foreach ($values as $v) {
            if (! in_array($v, $options, true)) {
                return false;
            }
        }

        return true;
    }
}
