<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryLine;

class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    public function definition(): array
    {
        return [
            'entry_id' => JournalEntry::factory(),
            'account_id' => ChartOfAccount::factory(),
            'description' => $this->faker->sentence(),
            'debit' => $this->faker->randomElement([0, $this->faker->numberBetween(100, 50000)]),
            'credit' => $this->faker->randomElement([0, $this->faker->numberBetween(100, 50000)]),
            'currency' => 'USD',
            'amount_currency' => 0,
        ];
    }

    public function debit(int $amount = 1000)
    {
        return $this->state(fn (array $attributes) => [
            'debit' => $amount,
            'credit' => 0,
        ]);
    }

    public function credit(int $amount = 1000)
    {
        return $this->state(fn (array $attributes) => [
            'debit' => 0,
            'credit' => $amount,
        ]);
    }
}
