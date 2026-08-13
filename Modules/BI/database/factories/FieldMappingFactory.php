<?php

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\FieldMapping;

class FieldMappingFactory extends Factory
{
    protected $model = FieldMapping::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'source_field' => fake()->word(),
            'target_field' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }
}