<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankStatement;

/** @extends Factory<BankStatement> */
class BankStatementFactory extends Factory
{
    protected $model = BankStatement::class;

    public function definition(): array
    {
        $opening = fake()->randomFloat(4, 0, 50000);
        $closing = fake()->randomFloat(4, 0, 50000);

        return [
            'bank_account_id' => BankAccount::factory(),
            'statement_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'status' => 'imported',
            'transaction_count' => 0,
            'matched_count' => 0,
            'reconciled_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function reconciled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reconciled',
            'reconciled_at' => now(),
        ]);
    }
}
