<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\UrgencyFactor;

class UrgencyFactorFactory extends Factory
{
    protected $model = UrgencyFactor::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'wait_time_hours' => fake()->word(),
            'sentiment_factor' => fake()->word(),
            'issue_complexity_factor' => fake()->word(),
            'agent_skill_factor' => fake()->word(),
            'customer_vip_factor' => fake()->word(),
            'sla_breach_factor' => fake()->word(),
            'repeat_issue_factor' => fake()->word(),
            'channel_factor' => fake()->word(),
            'business_hours_factor' => fake()->word(),
            'concurrent_escalations_factor' => fake()->word(),
            'total_urgency_score' => fake()->word(),
            'factor_breakdown' => fake()->word(),
            'calculated_at' => fake()->word(),
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