<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\EscalationWorkflow;

class EscalationWorkflowFactory extends Factory
{
    protected $model = EscalationWorkflow::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'description' => fake()->text(),
            'escalation_level' => fake()->word(),
            'min_urgency_score' => fake()->word(),
            'approval_required' => fake()->word(),
            'approval_roles' => fake()->word(),
            'escalation_time_minutes' => fake()->word(),
            'next_level' => fake()->word(),
            'send_notifications' => fake()->word(),
            'notification_recipients' => fake()->word(),
            'notification_template' => fake()->word(),
            'reassignment_rules' => fake()->word(),
            'priority_level' => fake()->word(),
            'is_active' => true,
            'order' => fake()->word(),
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