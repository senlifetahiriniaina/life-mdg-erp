<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\EdiTransaction;

class EdiTransactionFactory extends Factory
{
    protected $model = EdiTransaction::class;

    public function definition(): array
    {
        return [
            'type'        => $this->faker->randomElement(['850', '856', '810']),
            'direction'   => $this->faker->randomElement(['inbound', 'outbound']),
            'content_raw' => 'ISA*00*          *00*          *ZZ*SENDER*ZZ*RECEIVER*240101*1200*^*00501*000000001*0*P*>',
            'parsed_json' => ['po_number' => 'PO-' . $this->faker->randomNumber(5)],
            'status'      => $this->faker->randomElement(['received', 'processed', 'error']),
            'partner_id'  => null,
            'occurred_at' => now(),
        ];
    }
}
