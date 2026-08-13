<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'id' => fake()->word(),
            'slug' => fake()->slug(),
            'name' => fake()->word(),
            'company_name' => fake()->word(),
            'plan' => fake()->word(),
            'trial_ends_at' => fake()->word(),
            'settings' => fake()->word(),
            'onboarding_completed_at' => fake()->word(),
            'is_active' => true,
            'domain' => fake()->word(),
            'data' => fake()->word(),
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
        ]);
    }
}