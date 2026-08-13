<?php

declare(strict_types=1);

namespace Modules\Core\Traits;

use Illuminate\Support\Collection;
use Modules\Core\Models\CustomField;
use Modules\Core\Models\CustomFieldValue;

trait HasCustomFields
{
    /**
     * Return the entity_type identifier (defaults to the model's table name).
     * Override in the model to use a different identifier.
     */
    public function getEntityType(): string
    {
        return $this->getTable();
    }

    /**
     * Get all active custom field definitions for this entity type, ordered by sort_order.
     */
    public function getCustomFieldDefinitions(): Collection
    {
        return CustomField::where('entity_type', $this->getEntityType())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get all custom field values for this record as a field_key → cast value map.
     *
     * @return array<string, mixed>
     */
    public function getCustomValues(): array
    {
        $values = CustomFieldValue::where('entity_type', $this->getEntityType())
            ->where('entity_id', $this->id)
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
     * Set (create or update) a custom field value for this record.
     */
    public function setCustomValue(string $fieldKey, mixed $value): CustomFieldValue
    {
        $field = CustomField::where('entity_type', $this->getEntityType())
            ->where('field_key', $fieldKey)
            ->firstOrFail();

        return CustomFieldValue::updateOrCreate(
            [
                'custom_field_id' => $field->id,
                'entity_type' => $this->getEntityType(),
                'entity_id' => $this->id,
            ],
            ['value' => $value !== null ? (string) $value : null]
        );
    }

    /**
     * Get a single custom field value by key, returning the default if not set.
     */
    public function getCustomValue(string $fieldKey): mixed
    {
        $field = CustomField::where('entity_type', $this->getEntityType())
            ->where('field_key', $fieldKey)
            ->first();

        if (! $field) {
            return null;
        }

        $val = CustomFieldValue::where('custom_field_id', $field->id)
            ->where('entity_type', $this->getEntityType())
            ->where('entity_id', $this->id)
            ->first();

        return $val
            ? $val->castValue()
            : $field->castValue((string) ($field->default_value ?? ''));
    }

    /**
     * Delete all custom field values for this record.
     * Call this in the model's `deleting` event to clean up orphaned values.
     */
    public function deleteCustomValues(): void
    {
        CustomFieldValue::where('entity_type', $this->getEntityType())
            ->where('entity_id', $this->id)
            ->delete();
    }
}
