<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TaxEntry;
use Modules\Accounting\Models\TaxRate;

/** @extends Factory<TaxEntry> */
class TaxEntryFactory extends Factory
{
    protected $model = TaxEntry::class;

    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-1 year', '-1 month');
        $periodEnd = clone $periodStart;
        $periodEnd->modify('+1 month');

        $taxableAmount = fake()->randomFloat(4, 100, 10000);
        $taxRate = fake()->randomFloat(4, 5, 25);
        $taxAmount = $taxableAmount * ($taxRate / 100);

        return [
            'tax_rate_id' => TaxRate::factory(),
            'invoice_id' => null,
            'journal_entry_id' => null,
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'type' => fake()->randomElement(['collected', 'paid']),
        ];
    }

    public function collected(): static
    {
        return $this->state(['type' => 'collected']);
    }

    public function paid(): static
    {
        return $this->state(['type' => 'paid']);
    }
}
