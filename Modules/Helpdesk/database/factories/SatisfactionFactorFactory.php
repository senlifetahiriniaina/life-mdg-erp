<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\SatisfactionFactor;

class SatisfactionFactorFactory extends Factory
{
    protected $model = SatisfactionFactor::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'resolution_time_minutes' => fake()->word(),
            'resolution_time_factor' => fake()->word(),
            'first_contact_resolution' => fake()->word(),
            'agent_professionalism' => fake()->word(),
            'agent_professionalism_factor' => fake()->word(),
            'agent_friendliness' => fake()->word(),
            'agent_friendliness_factor' => fake()->word(),
            'agent_knowledge_level' => fake()->word(),
            'agent_knowledge_factor' => fake()->word(),
            'communication_quality' => fake()->word(),
            'communication_factor' => fake()->word(),
            'problem_understanding' => fake()->word(),
            'problem_understanding_factor' => fake()->word(),
            'solution_effectiveness' => fake()->word(),
            'solution_effectiveness_factor' => fake()->word(),
            'customer_expectation_met' => fake()->word(),
            'expectation_factor' => fake()->word(),
            'follow_up_quality_rating' => fake()->word(),
            'follow_up_factor' => fake()->word(),
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