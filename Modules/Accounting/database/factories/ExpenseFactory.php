<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Expense;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'company_id' => 1,
            'expense_number' => strtoupper($this->faker->unique()->lexify('EXP-????')),
            'employee_id' => 1,
            'expense_category_id' => 1,
            'expense_date' => $this->faker->date(),
            'description' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'currency' => 'USD',
            'tax_amount' => $this->faker->randomFloat(2, 0, 1000),
            'status' => $this->faker->randomElement(['draft', 'pending', 'approved', 'reimbursed']),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
        ];
    }
}
