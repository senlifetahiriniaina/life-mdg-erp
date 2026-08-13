<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\TerritoryQuota;

class TerritoryQuotaFactory extends Factory
{
    protected $model = TerritoryQuota::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'territory_id' => fake()->word(),
            'period' => fake()->word(),
            'quota_revenue' => fake()->word(),
            'quota_deals' => fake()->word(),
            'actual_revenue' => fake()->word(),
            'actual_deals' => fake()->word(),
            'attainment_pct' => fake()->word(),
            'forecast_revenue' => fake()->word(),
            'days_remaining' => fake()->word(),
            'metadata' => fake()->word(),
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