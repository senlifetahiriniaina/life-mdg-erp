<?php

namespace Modules\Setup\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\SourceSchema;

class SourceSchemaFactory extends Factory
{
    protected $model = SourceSchema::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'import_job_id' => fake()->word(),
            'detected_encoding' => fake()->word(),
            'detected_delimiter' => fake()->word(),
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