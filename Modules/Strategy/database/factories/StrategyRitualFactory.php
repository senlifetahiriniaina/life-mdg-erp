<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyRitual;

class StrategyRitualFactory extends Factory
{
    protected $model = StrategyRitual::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'name' => fake()->word(),
            'type' => fake()->randomElement(['weekly_checkin', 'monthly_review', 'quarterly_review', 'annual_planning']),
            'cadence' => fake()->randomElement(['weekly', 'biweekly', 'monthly', 'quarterly', 'annual']),
            'day_of_week' => fake()->numberBetween(0, 6),
            'day_of_month' => fake()->numberBetween(1, 28),
            'attendee_roles' => fake()->randomElements(['ceo', 'cfo', 'coo', 'manager', 'team_lead'], 2),
            'plan_id' => StrategyPlan::factory(),
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
     * Indicate model is archived (no-op: strategy_rituals has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
