<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\IntercompanyTransaction;

/** @extends Factory<IntercompanyTransaction> */
class IntercompanyTransactionFactory extends Factory
{
    protected $model = IntercompanyTransaction::class;

    public function definition(): array
    {
        return [
            'from_company_id' => Company::factory(),
            'to_company_id' => Company::factory(),
            'journal_entry_id' => null,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'currency' => 'USD',
            'description' => fake()->sentence(),
            'is_eliminated' => false,
            'eliminated_at' => null,
        ];
    }
}
