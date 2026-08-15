<?php

namespace Modules\Strategy\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyRitual;
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
            'ritual_id' => StrategyRitual::factory(),
            'scheduled_at' => fake()->dateTime(),
            'started_at' => fake()->dateTime(),
            'completed_at' => fake()->dateTime(),
            'facilitator_id' => User::factory(),
            'agenda' => fake()->words(3),
            'decisions' => fake()->words(3),
            'action_items' => fake()->words(3),
            'ai_summary' => fake()->paragraph(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_ritual_sessions has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_ritual_sessions has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
