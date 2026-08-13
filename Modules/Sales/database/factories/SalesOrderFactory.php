<?php

namespace Modules\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\SalesOrder;

class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'reference' => fake()->bothify('??-##'),
            'contact_id' => fake()->word(),
            'account_id' => fake()->word(),
            'opportunity_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'currency' => fake()->word(),
            'subtotal' => fake()->word(),
            'discount_amount' => fake()->word(),
            'tax_amount' => fake()->word(),
            'total' => fake()->word(),
            'notes' => fake()->text(),
            'shipping_address' => fake()->word(),
            'expected_delivery_date' => fake()->word(),
            'confirmed_at' => fake()->word(),
            'cancelled_at' => fake()->word(),
            'created_by' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }
}