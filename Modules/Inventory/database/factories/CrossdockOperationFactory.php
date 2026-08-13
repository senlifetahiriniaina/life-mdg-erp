<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\CrossdockOperation;
use Modules\Inventory\Models\Product;

/** @extends Factory<CrossdockOperation> */
class CrossdockOperationFactory extends Factory
{
    protected $model = CrossdockOperation::class;

    public function definition(): array
    {
        return [
            'inbound_shipment_id' => fake()->optional()->numberBetween(1, 100),
            'outbound_order_id' => fake()->optional()->numberBetween(1, 100),
            'product_id' => Product::factory(),
            'qty' => fake()->randomFloat(2, 1, 100),
            'status' => fake()->randomElement(['planned', 'executed', 'cancelled']),
            'executed_at' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'planned',
            'executed_at' => null,
        ]);
    }

    public function executed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'executed',
            'executed_at' => now(),
        ]);
    }
}
