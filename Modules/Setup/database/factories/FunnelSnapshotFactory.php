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
                        'tenant_id' => fake()->word(),
            'snapshot_date' => fake()->word(),
            'sessions_started' => fake()->word(),
            'sessions_completed' => fake()->word(),
            'sessions_abandoned' => fake()->word(),
            'avg_duration_seconds' => fake()->word(),
            'median_duration_seconds' => fake()->word(),
            'step1_completion_rate' => fake()->word(),
            'step2_completion_rate' => fake()->word(),
            'step3_completion_rate' => fake()->word(),
            'step4_completion_rate' => fake()->word(),
            'step5_completion_rate' => fake()->word(),
            'ai_mapping_adoption_rate' => fake()->word(),
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