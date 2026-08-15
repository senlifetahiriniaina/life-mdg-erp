<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\RFQLine;

class RFQLineFactory extends Factory
{
    protected $model = RFQLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'rfq_id' => RFQ::factory(),
            'description' => fake()->text(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit' => fake()->randomElement(['pcs', 'kg', 'box', 'liter', 'unit']),
            'required_date' => fake()->dateTime('+30 days'),
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
