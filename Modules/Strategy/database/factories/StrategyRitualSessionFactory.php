<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyRitualSession;

class StrategyRitualSessionFactory extends Factory
{
    protected $model = StrategyRitualSession::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ritual_id' => fake()->word(),
            'scheduled_at' => fake()->word(),
            'started_at' => fake()->word(),
            'completed_at' => fake()->word(),
            'facilitator_id' => fake()->word(),
            'agenda' => fake()->word(),
            'decisions' => fake()->word(),
            'action_items' => fake()->word(),
            'ai_summary' => fake()->word(),
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