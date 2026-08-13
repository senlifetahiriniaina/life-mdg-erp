<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\RoutingRule;

class RoutingRuleFactory extends Factory
{
    protected $model = RoutingRule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'team_id' => fake()->word(),
            'name' => fake()->word(),
            'rule_type' => fake()->word(),
            'sentiment_trigger' => fake()->word(),
            'target_queue' => fake()->word(),
            'priority_boost' => fake()->word(),
            'requires_specialist' => fake()->word(),
            'skill_required' => fake()->word(),
            'routing_conditions' => fake()->word(),
            'escalation_path' => fake()->word(),
            'max_wait_minutes' => fake()->word(),
            'sla_hours_override' => fake()->word(),
            'notify_customer' => fake()->word(),
            'notification_message' => fake()->word(),
            'is_active' => true,
            'order' => fake()->word(),
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