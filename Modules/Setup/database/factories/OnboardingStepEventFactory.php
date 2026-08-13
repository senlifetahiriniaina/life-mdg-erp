<?php

namespace Modules\Setup\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
            'onboarding_session_id' => fake()->word(),
            'duration_seconds' => fake()->word(),
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