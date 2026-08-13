<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\BankAccount;

/** @extends Factory<BankAccount> */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Account',
            'bank_name' => fake()->randomElement(['Chase', 'Wells Fargo', 'Bank of America', 'Citibank', 'HSBC']),
            'account_number' => fake()->optional()->numerify('##########'),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            'current_balance' => fake()->randomFloat(4, 0, 100000),
            'last_reconciled_at' => null,
            'last_reconciled_balance' => 0,
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
