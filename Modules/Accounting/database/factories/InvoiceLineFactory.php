<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;

class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 100);
        $unitPrice = $this->faker->numberBetween(100, 10000);
        $subtotal = $quantity * $unitPrice;
        $taxRate = 18;
        $taxAmount = (int) round($subtotal * $taxRate / 100);

        return [
            'invoice_id' => Invoice::factory(),
            'account_id' => null,
            'product_id' => null,
            'description' => $this->faker->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'match_ref' => null,
            'matched_by' => null,
            'matched_at' => null,
        ];
    }
}
