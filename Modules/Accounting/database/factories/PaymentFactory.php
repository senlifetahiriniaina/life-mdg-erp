<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'payment_date' => $this->faker->date(),
            'amount' => $this->faker->numberBetween(1000, 100000),
            'currency' => 'XOF',
            'payment_method' => $this->faker->randomElement(['bank', 'cash', 'mobile_money', 'cheque']),
            'reference' => strtoupper($this->faker->bothify('PAY-####')),
            'status' => 'completed',
            'notes' => null,
            'created_by' => null,
        ];
    }
}
