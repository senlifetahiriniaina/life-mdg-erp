<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\TeamBenchmarking;

class TeamBenchmarkingFactory extends Factory
{
    protected $model = TeamBenchmarking::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'team_id' => fake()->word(),
            'benchmark_date' => fake()->word(),
            'team_size' => fake()->word(),
            'avg_satisfaction_rating' => fake()->word(),
            'median_satisfaction_rating' => fake()->word(),
            'top_performer_satisfaction' => fake()->word(),
            'bottom_performer_satisfaction' => fake()->word(),
            'avg_resolution_time_minutes' => fake()->word(),
            'best_resolution_time_minutes' => fake()->word(),
            'worst_resolution_time_minutes' => fake()->word(),
            'avg_escalation_rate' => fake()->word(),
            'avg_first_contact_resolution_rate' => fake()->word(),
            'avg_nps_score' => fake()->word(),
            'avg_quality_score' => fake()->word(),
            'avg_productivity_score' => fake()->word(),
            'top_performer_rank' => fake()->word(),
            'bottom_performer_rank' => fake()->word(),
            'performance_distribution' => fake()->word(),
            'strengths' => fake()->word(),
            'improvement_areas' => fake()->word(),
            'team_trend' => fake()->word(),
            'team_performance_rating' => fake()->word(),
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