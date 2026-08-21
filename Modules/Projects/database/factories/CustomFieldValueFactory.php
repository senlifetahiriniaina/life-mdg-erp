<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\CustomFieldValue;

/** @extends Factory<CustomFieldValue> */
class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    public function definition(): array
    {
        return [
            'custom_field_id' => CustomFieldFactory::new(),
            'entity_type' => 'task',
            'entity_id' => TaskFactory::new(),
            'value' => fake()->word(),
        ];
    }
}
