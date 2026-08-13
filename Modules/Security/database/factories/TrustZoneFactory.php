<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\TrustZone;

class TrustZoneFactory extends Factory
{
    protected $model = TrustZone::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'zone_name' => fake()->word(),
            'zone_type' => fake()->word(),
            'description' => fake()->text(),
            'cidr_blocks' => fake()->word(),
            'device_policies' => fake()->word(),
            'authentication_policies' => fake()->word(),
            'trust_score_minimum' => fake()->word(),
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