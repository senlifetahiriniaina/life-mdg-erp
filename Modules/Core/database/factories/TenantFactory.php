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
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
            'company_name' => fake()->company(),
            'country_code' => fake()->randomElement(['MG', 'SN', 'CI', 'CM']),
            'currency' => fake()->randomElement(['MGA', 'XOF', 'XAF']),
            'locale' => fake()->randomElement(['fr', 'en', 'mg']),
            'plan' => fake()->randomElement(['starter', 'pro', 'enterprise']),
            'status' => fake()->randomElement(['trial', 'active', 'suspended']),
            'trial_ends_at' => fake()->dateTimeBetween('now', '+30 days'),
            'settings' => [],
            'onboarding_step' => fake()->numberBetween(0, 5),
            'onboarding_completed_at' => null,
            'contact_email' => fake()->companyEmail(),
            'is_active' => true,
            'domain' => fake()->domainName(),
            'data' => [],
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