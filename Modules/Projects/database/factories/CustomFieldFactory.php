<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\CustomField;

/** @extends Factory<CustomField> */
class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'entity_type' => fake()->randomElement(CustomField::ENTITY_TYPES),
            'field_name' => strtolower($name),
            'field_label' => ucfirst($name),
            'field_type' => fake()->randomElement(CustomField::FIELD_TYPES),
            'options' => null,
            'is_required' => false,
            'sort_order' => 0,
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
