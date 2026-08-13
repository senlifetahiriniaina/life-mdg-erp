<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\ThreatIndicator;

class ThreatIndicatorFactory extends Factory
{
    protected $model = ThreatIndicator::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'indicator_type' => fake()->word(),
            'indicator_value' => fake()->word(),
            'threat_level' => fake()->word(),
            'description' => fake()->text(),
            'source' => fake()->word(),
            'is_whitelisted' => fake()->word(),
            'detected_at' => fake()->word(),
            'expires_at' => fake()->word(),
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