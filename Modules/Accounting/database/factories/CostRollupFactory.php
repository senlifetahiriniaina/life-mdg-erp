<?php

namespace Modules\Accounting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\app\Models\CostRollup;

class CostRollupFactory extends Factory
{
    protected $model = CostRollup::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'entity_type' => fake()->word(),
            'entity_id' => fake()->word(),
            'entity_name' => fake()->word(),
            'period' => fake()->word(),
            'capex_total' => fake()->word(),
            'opex_total' => fake()->word(),
            'finex_total' => fake()->word(),
            'riskex_total' => fake()->word(),
            'total_cost' => fake()->word(),
            'currency' => fake()->word(),
            'unit_cost' => fake()->word(),
            'margin' => fake()->word(),
            'margin_pct' => fake()->word(),
            'computed_at' => fake()->word(),
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