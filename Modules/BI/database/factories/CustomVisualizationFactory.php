<?php

namespace Modules\BI\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\app\Models\CustomVisualization;

class CustomVisualizationFactory extends Factory
{
    protected $model = CustomVisualization::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'created_by' => fake()->word(),
            'dashboard_id' => fake()->word(),
            'template_id' => fake()->word(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'type' => fake()->word(),
            'config' => fake()->word(),
            'data_source' => fake()->word(),
            'color_scale' => fake()->word(),
            'range_config' => fake()->word(),
            'real_time_enabled' => fake()->word(),
            'refresh_interval' => fake()->word(),
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