<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\IndustryBenchmark;

class IndustryBenchmarkFactory extends Factory
{
    protected $model = IndustryBenchmark::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'ratio_name' => fake()->word(),
            'industry' => fake()->word(),
            'country' => fake()->countryCode(),
            'p25' => fake()->randomFloat(4, 0, 100),
            'median' => fake()->randomFloat(4, 0, 100),
            'p75' => fake()->randomFloat(4, 0, 100),
            'year' => fake()->numberBetween(2020, 2026),
            'source' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_industry_benchmarks has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_industry_benchmarks has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
