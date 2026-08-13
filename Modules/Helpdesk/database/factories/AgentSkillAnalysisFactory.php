<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\AgentSkillAnalysis;

class AgentSkillAnalysisFactory extends Factory
{
    protected $model = AgentSkillAnalysis::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'agent_id' => fake()->word(),
            'skill_category' => fake()->word(),
            'skill_name' => fake()->word(),
            'skill_level' => fake()->word(),
            'proficiency_score' => fake()->word(),
            'tickets_handled_for_skill' => fake()->word(),
            'avg_satisfaction_for_skill' => fake()->word(),
            'first_contact_resolution_rate_for_skill' => fake()->word(),
            'avg_resolution_time_for_skill_minutes' => fake()->word(),
            'escalation_count_for_skill' => fake()->word(),
            'escalation_rate_for_skill' => fake()->word(),
            'skill_improvement_points' => fake()->word(),
            'skill_certified_at' => fake()->word(),
            'skill_last_practiced_at' => fake()->word(),
            'proficiency_trend' => fake()->word(),
            'days_since_practice' => fake()->word(),
            'needs_training' => fake()->word(),
            'training_recommendations' => fake()->word(),
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