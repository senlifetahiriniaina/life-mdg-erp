<?php

namespace Modules\Setup\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\OnboardingSession;
use Modules\Setup\Models\OnboardingStepEvent;

class OnboardingStepEventFactory extends Factory
{
    protected $model = OnboardingStepEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->numberBetween(1, 500),
            'user_id' => User::factory(),
            'onboarding_session_id' => OnboardingSession::factory(),
            'step' => fake()->numberBetween(1, 5),
            'event' => fake()->randomElement(['started', 'completed', 'back', 'skipped', 'error']),
            'duration_seconds' => fake()->numberBetween(5, 300),
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