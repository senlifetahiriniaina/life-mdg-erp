<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\FreightInvoice;

/** @extends Factory<FreightInvoice> */
class FreightInvoiceFactory extends Factory
{
    protected $model = FreightInvoice::class;

    public function definition(): array
    {
        return [
            'invoice_number' => 'FRT-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'type' => 'payable',
            'status' => 'draft',
            'invoiced_amount' => fake()->randomFloat(2, 100, 10000),
            'currency' => 'USD',
            'invoice_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
        ];
    }
}
