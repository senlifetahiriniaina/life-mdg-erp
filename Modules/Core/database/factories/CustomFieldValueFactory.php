<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\CustomField;
use Modules\Core\Models\CustomFieldValue;

class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    public function definition(): array
    {
        return [
            'custom_field_id' => CustomField::factory(),
            'entity_type' => 'crm_contacts',
            'entity_id' => $this->faker->numberBetween(1, 1000),
            'value' => $this->faker->word(),
        ];
    }

    public function forField(CustomField $field): static
    {
        return $this->state([
            'custom_field_id' => $field->id,
            'entity_type' => $field->entity_type,
        ]);
    }

    public function forEntity(string $entityType, int $entityId): static
    {
        return $this->state([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }
}
