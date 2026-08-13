<?php

namespace Modules\Setup\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\app\Models\OnboardingSession;

class OnboardingSessionFactory extends Factory
{
    protected $model = OnboardingSession::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'user_id' => fake()->word(),
            'started_at' => fake()->word(),
            'completed_at' => fake()->word(),
            'abandoned_at' => fake()->word(),
            'current_step' => fake()->word(),
            'total_duration_seconds' => fake()->word(),
            'source_type' => fake()->word(),
            'rows_imported' => fake()->word(),
            'ai_mapping_used' => fake()->word(),
            'ai_mapping_accepted_percent' => fake()->word(),
            'errors_count' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
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