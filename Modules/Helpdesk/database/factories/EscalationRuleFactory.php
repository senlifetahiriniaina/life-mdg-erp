<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\EscalationRule;

class EscalationRuleFactory extends Factory
{
    protected $model = EscalationRule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'sla_policy_id' => fake()->word(),
            'name' => fake()->word(),
            'trigger_type' => fake()->word(),
            'trigger_hours' => fake()->word(),
            'action_type' => fake()->word(),
            'action_config' => fake()->word(),
            'is_active' => true,
            'priority' => fake()->word(),
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