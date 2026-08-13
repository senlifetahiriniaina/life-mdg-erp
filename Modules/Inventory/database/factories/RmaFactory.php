<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Rma;

/** @extends Factory<Rma> */
class RmaFactory extends Factory
{
    protected $model = Rma::class;

    public function definition(): array
    {
        return [
            'reference' => 'RMA-'.strtoupper(fake()->unique()->bothify('####??')),
            'order_id' => fake()->optional()->numberBetween(1, 500),
            'customer_name' => fake()->name(),
            'reason' => fake()->sentence(),
            'status' => fake()->randomElement(['requested', 'approved', 'received', 'inspected', 'refunded', 'closed']),
            'items' => [
                ['product_id' => 1, 'qty' => fake()->numberBetween(1, 5), 'price' => fake()->randomFloat(2, 10, 200)],
            ],
            'return_method' => fake()->randomElement(['refund', 'exchange', 'credit']),
            'approved_at' => null,
            'received_at' => null,
            'refunded_at' => null,
        ];
    }

    public function requested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'requested',
            'approved_at' => null,
            'received_at' => null,
            'refunded_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}
