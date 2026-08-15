<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\Ratio;
use Modules\Strategy\Models\StrategicAlert;
use Modules\Strategy\Models\StrategyKpi;

class StrategicAlertFactory extends Factory
{
    protected $model = StrategicAlert::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'type' => 'ratio_below_target',
            'severity' => fake()->randomElement(['info', 'warning', 'critical']),
            'message' => fake()->sentence(),
            'kpi_id' => StrategyKpi::factory(),
            'ratio_id' => Ratio::factory(),
            'triggered_at' => fake()->dateTime(),
            'resolved_at' => null,
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_alerts has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_alerts has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
