<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyObjective;

class StrategyObjectiveFactory extends Factory
{
    protected $model = StrategyObjective::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'plan_id' => fake()->word(),
            'pillar_id' => fake()->word(),
            'parent_id' => fake()->word(),
            'level' => fake()->word(),
            'owner_type' => fake()->word(),
            'owner_id' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'framework_type' => fake()->word(),
            'bsc_perspective' => fake()->word(),
            'weight' => fake()->word(),
            'start_date' => fake()->dateTime(),
            'end_date' => fake()->dateTime(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'progress' => fake()->word(),
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