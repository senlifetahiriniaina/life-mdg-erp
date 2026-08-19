<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\FinancialSimulation;
use Modules\Accounting\Models\FinancialSimulationLine;

class FinancialSimulationLineFactory extends Factory
{
    protected $model = FinancialSimulationLine::class;

    public function definition(): array
    {
        return [
            'financial_simulation_id' => FinancialSimulation::factory(),
            'type' => $this->faker->randomElement(['sale', 'purchase']),
            'product_id' => null,
            'label' => $this->faker->words(3, true),
            'quantity' => $this->faker->randomFloat(2, 1, 50),
            'unit_price' => $this->faker->randomFloat(2, 1000, 500000),
            'recurrence' => $this->faker->randomElement(['once', 'weekly', 'monthly']),
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'growth_rate_percent' => 0,
            'counterpart_account_code' => null,
            'status' => 'simulated',
            'realized_at' => null,
            'realized_type' => null,
            'realized_id' => null,
            'notes' => null,
        ];
    }
}
