<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(1000, 100000);
        $taxAmount = $subtotal * 0.10;
        $total = $subtotal + $taxAmount;

        return [
            'journal_id' => Journal::factory(),
            'created_by' => User::factory(),
            'number' => strtoupper($this->faker->unique()->lexify('INV-????')),
            'type' => $this->faker->randomElement(['invoice', 'bill', 'credit_note', 'debit_note']),
            'partner_id' => null,
            'partner_name' => $this->faker->company(),
            'partner_type' => $this->faker->randomElement(['customer', 'vendor']),
            'invoice_date' => now()->startOfYear()->toDateString(),
            'due_date' => $this->faker->dateTimeBetween('now', '+2 months'),
            'status' => 'draft',
            'currency' => 'USD',
            'exchange_rate' => 1,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'amount_paid' => 0,
            'amount_due' => $total,
            'notes' => $this->faker->sentence(),
        ];
    }
}
