<?php

namespace Modules\Achats\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\RFQ;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Models\SupplierQuote;

class SupplierQuoteFactory extends Factory
{
    protected $model = SupplierQuote::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'rfq_id' => RFQ::factory(),
            'supplier_id' => Supplier::factory(),
            'quote_number' => fake()->unique()->bothify('QT-#####'),
            'unit_price' => fake()->randomFloat(4, 1, 1000),
            'total_price' => fake()->randomFloat(2, 100, 100000),
            'delivery_days' => fake()->numberBetween(1, 90),
            'terms' => fake()->sentence(),
            'validity_date' => fake()->dateTime('+30 days'),
            'status' => fake()->randomElement(['pending', 'submitted', 'accepted', 'rejected']),
            'created_by' => User::factory(),
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
