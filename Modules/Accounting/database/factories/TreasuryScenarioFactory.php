<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TreasuryForecast;
use Modules\Accounting\Models\TreasuryScenario;

/** @extends Factory<TreasuryScenario> */
class TreasuryScenarioFactory extends Factory
{
    protected $model = TreasuryScenario::class;

    public function definition(): array
    {
        return [
            'forecast_id' => TreasuryForecast::factory(),
            'name' => 'Base Scenario',
            'type' => 'base',
            'adjustment_factor' => 1.0,
            'scenario_balance' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function base(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Base Scenario',
            'type' => 'base',
            'adjustment_factor' => 1.0,
        ]);
    }

    public function optimistic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Optimistic Scenario',
            'type' => 'optimistic',
            'adjustment_factor' => 1.2,
        ]);
    }

    public function pessimistic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pessimistic Scenario',
            'type' => 'pessimistic',
            'adjustment_factor' => 0.8,
        ]);
    }
}
