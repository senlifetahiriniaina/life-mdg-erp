<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\Ratio;

class RatioFactory extends Factory
{
    protected $model = Ratio::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'module' => fake()->word(),
            'name' => fake()->word(),
            'numerator_kpi_id' => fake()->word(),
            'denominator_kpi_id' => fake()->word(),
            'formula' => fake()->word(),
            'benchmark_category' => fake()->word(),
            'description' => fake()->text(),
            'unit' => fake()->word(),
            'direction' => fake()->word(),
            'target_min' => fake()->word(),
            'target_max' => fake()->word(),
            'title' => fake()->word(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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