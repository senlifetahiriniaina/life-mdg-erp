<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\FinancialSimulation;

class FinancialSimulationFactory extends Factory
{
    protected $model = FinancialSimulation::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => 'Simulation ' . $this->faker->words(2, true),
            'description' => null,
            'granularity' => $this->faker->randomElement(['week', 'month']),
            'start_date' => now()->startOfMonth()->toDateString(),
            'horizon_periods' => 12,
            'opening_cash_balance' => null,
            'status' => 'draft',
            'created_by' => null,
        ];
    }
}
