<?php

namespace Modules\Achats\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\app\Models\SupplierQuote;

class SupplierQuoteFactory extends Factory
{
    protected $model = SupplierQuote::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'rfq_id' => fake()->word(),
            'supplier_id' => fake()->word(),
            'quote_number' => fake()->word(),
            'unit_price' => fake()->word(),
            'total_price' => fake()->word(),
            'delivery_days' => fake()->word(),
            'terms' => fake()->word(),
            'validity_date' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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