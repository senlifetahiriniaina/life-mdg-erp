<?php

namespace Modules\CRM\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\app\Models\RevenueTrend;

class RevenueTrendFactory extends Factory
{
    protected $model = RevenueTrend::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'metric_name' => fake()->word(),
            'dimension' => fake()->word(),
            'dimension_value' => fake()->word(),
            'period_start' => fake()->word(),
            'period_end' => fake()->word(),
            'current_value' => fake()->word(),
            'previous_value' => fake()->word(),
            'change_pct' => fake()->word(),
            'trend_direction' => fake()->word(),
            'data_points_count' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
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