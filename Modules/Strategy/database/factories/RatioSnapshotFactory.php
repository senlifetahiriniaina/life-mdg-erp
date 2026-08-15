<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\Ratio;
use Modules\Strategy\Models\RatioSnapshot;

class RatioSnapshotFactory extends Factory
{
    protected $model = RatioSnapshot::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'ratio_id' => Ratio::factory(),
            'tenant_id' => fake()->uuid(),
            'period' => fake()->date('Y-m'),
            'value' => fake()->randomFloat(4, 0, 1000),
            'benchmark_value' => fake()->randomFloat(4, 0, 1000),
            'gap' => fake()->randomFloat(4, -100, 100),
            'created_at' => fake()->dateTime(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_ratio_snapshots has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_ratio_snapshots has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
