<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\CustomField;

class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    public function definition(): array
    {
        return [
            'entity_type' => 'crm_contacts',
            'field_key' => $this->faker->unique()->slug(2),
            'field_label' => ucwords(implode(' ', $this->faker->words(2))),
            'field_type' => 'text',
            'is_required' => false,
            'is_unique' => false,
            'is_searchable' => true,
            'default_value' => null,
            'options' => null,
            'validation_rules' => null,
            'group_name' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function forEntity(string $entityType): static
    {
        return $this->state(['entity_type' => $entityType]);
    }

    public function ofType(string $fieldType): static
    {
        return $this->state(['field_type' => $fieldType]);
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
