<?php

namespace Modules\Setup\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\OnboardingSession;

class OnboardingSessionFactory extends Factory
{
    protected $model = OnboardingSession::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->numberBetween(1, 500),
            'user_id' => User::factory(),
            'started_at' => fake()->dateTime(),
            'completed_at' => null,
            'abandoned_at' => null,
            'current_step' => fake()->numberBetween(1, 5),
            'total_duration_seconds' => fake()->numberBetween(60, 900),
            'source_type' => fake()->randomElement(['file_csv', 'file_excel', 'file_pdf', 'db_migration', 'manual']),
            'rows_imported' => fake()->numberBetween(0, 1000),
            'ai_mapping_used' => fake()->boolean(),
            'ai_mapping_accepted_percent' => fake()->randomFloat(2, 0, 100),
            'errors_count' => fake()->numberBetween(0, 20),
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