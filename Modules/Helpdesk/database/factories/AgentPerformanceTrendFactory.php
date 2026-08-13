<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\AgentPerformanceTrend;

class AgentPerformanceTrendFactory extends Factory
{
    protected $model = AgentPerformanceTrend::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'agent_id' => fake()->word(),
            'period_type' => fake()->word(),
            'period_start_date' => fake()->word(),
            'period_end_date' => fake()->word(),
            'satisfaction_trend' => fake()->word(),
            'resolution_time_trend' => fake()->word(),
            'productivity_trend' => fake()->word(),
            'quality_trend' => fake()->word(),
            'escalation_trend' => fake()->word(),
            'trend_summary' => fake()->word(),
            'performance_direction' => fake()->word(),
            'improvement_points' => fake()->word(),
            'decline_points' => fake()->word(),
            'top_improvements' => fake()->word(),
            'areas_needing_improvement' => fake()->word(),
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