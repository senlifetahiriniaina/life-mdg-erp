<?php

namespace Modules\Setup\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\app\Models\FieldMapping;

class FieldMappingFactory extends Factory
{
    protected $model = FieldMapping::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'import_job_id' => fake()->word(),
            'source_field' => fake()->word(),
            'target_field' => fake()->word(),
            'transform_type' => fake()->word(),
            'transform_config' => fake()->word(),
            'is_required' => fake()->word(),
            'ai_confidence' => fake()->word(),
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