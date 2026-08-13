<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\GLAccount;

class GLAccountFactory extends Factory
{
    protected $model = GLAccount::class;

    public function definition(): array
    {
        return [
            'account_number' => strtoupper($this->faker->unique()->lexify('ACCT????')),
            'account_name' => $this->faker->words(3, true),
            'account_type' => $this->faker->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']),
            'normal_balance' => $this->faker->randomElement(['debit', 'credit']),
            'description' => $this->faker->sentence(),
            'balance' => $this->faker->randomFloat(2, 0, 100000),
            'status' => 'active',
        ];
    }
}
