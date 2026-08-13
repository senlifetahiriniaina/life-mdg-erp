<?php

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\BiAlert;

class BiAlertFactory extends Factory
{
    protected $model = BiAlert::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'condition_type' => fake()->word(),
            'threshold' => fake()->word(),
            'metric_name' => fake()->word(),
            'check_interval_minutes' => fake()->word(),
            'channels' => fake()->word(),
            'recipients' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'is_active' => true,
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
        ]);
    }
}