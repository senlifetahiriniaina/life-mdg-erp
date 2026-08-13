<?php

namespace Modules\Sales\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\Models\SalesQuotation;

class SalesQuotationFactory extends Factory
{
    protected $model = SalesQuotation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'reference' => fake()->bothify('??-##'),
            'contact_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'currency' => fake()->word(),
            'total' => fake()->word(),
            'valid_until' => fake()->word(),
            'notes' => fake()->text(),
            'converted_to_order_id' => fake()->word(),
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