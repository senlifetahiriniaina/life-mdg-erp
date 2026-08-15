<?php

namespace Modules\Setup\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\FunnelSnapshot;

class FunnelSnapshotFactory extends Factory
{
    protected $model = FunnelSnapshot::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->numberBetween(1, 500),
            'snapshot_date' => fake()->date(),
            'sessions_started' => fake()->numberBetween(1, 200),
            'sessions_completed' => fake()->numberBetween(0, 200),
            'sessions_abandoned' => fake()->numberBetween(0, 50),
            'avg_duration_seconds' => fake()->numberBetween(60, 900),
            'median_duration_seconds' => fake()->numberBetween(60, 900),
            'step1_completion_rate' => fake()->randomFloat(2, 0, 100),
            'step2_completion_rate' => fake()->randomFloat(2, 0, 100),
            'step3_completion_rate' => fake()->randomFloat(2, 0, 100),
            'step4_completion_rate' => fake()->randomFloat(2, 0, 100),
            'step5_completion_rate' => fake()->randomFloat(2, 0, 100),
            'ai_mapping_adoption_rate' => fake()->randomFloat(2, 0, 100),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}