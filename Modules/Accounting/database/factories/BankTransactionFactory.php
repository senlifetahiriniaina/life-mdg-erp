<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankTransaction;

/** @extends Factory<BankTransaction> */
class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    public function definition(): array
    {
        return [
            'statement_id' => BankStatement::factory(),
            'transaction_date' => now()->subDays(rand(1, 30))->toDateString(),
            'description' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, -10000, 10000),
            'reference' => fake()->optional()->bothify('REF-####'),
            'status' => 'unmatched',
            'matched_entry_id' => null,
            'matched_at' => null,
        ];
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'matched',
            'matched_entry_id' => fake()->randomNumber(3),
            'matched_at' => now(),
        ]);
    }

    public function ignored(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'ignored']);
    }
}
