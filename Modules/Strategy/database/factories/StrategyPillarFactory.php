<?php

namespace Modules\Strategy\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\app\Models\StrategyPillar;

class StrategyPillarFactory extends Factory
{
    protected $model = StrategyPillar::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'plan_id' => fake()->word(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'color' => fake()->word(),
            'icon' => fake()->word(),
            'sort_order' => fake()->word(),
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