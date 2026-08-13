<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\RevenueContract;

class RevenueContractFactory extends Factory
{
    protected $model = RevenueContract::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $endDate = $this->faker->dateTimeBetween($startDate, '+1 year');

        return [
            'contract_number' => 'CONTRACT-' . $this->faker->unique()->numerify('######'),
            'contract_type' => $this->faker->randomElement(['Service', 'Product', 'Hybrid', 'SaaS']),
            'customer_id' => Customer::factory(),
            'contract_date' => $startDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'contract_value' => $this->faker->numberBetween(10000, 500000),
            'currency' => 'USD',
            'performance_obligation_type' => $this->faker->randomElement(['single_performance', 'multiple_performance', 'time_based', 'outcome_based']),
            'revenue_recognition_method' => $this->faker->randomElement(['point_in_time', 'over_time', 'milestone', 'proportional']),
            'has_variable_consideration' => false,
            'status' => 'active',
        ];
    }
}
