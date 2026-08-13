<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\ABTestRun;

class ABTestRunFactory extends Factory
{
    protected $model = ABTestRun::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'ml_model_id' => fake()->word(),
            'control_version_id' => fake()->word(),
            'variant_version_id' => fake()->word(),
            'test_name' => fake()->word(),
            'hypothesis' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'sample_size' => fake()->word(),
            'test_split' => fake()->word(),
            'statistical_significance' => fake()->word(),
            'confidence_level' => fake()->word(),
            'started_at' => fake()->word(),
            'ended_at' => fake()->word(),
            'results' => fake()->word(),
            'winner' => fake()->word(),
            'conclusion' => fake()->word(),
            'created_by' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'notes' => fake()->text(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}