<?php

namespace Modules\Achats\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\RFQ;

class RFQFactory extends Factory
{
    protected $model = RFQ::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'rfq_number' => fake()->unique()->bothify('RFQ-#####'),
            'status' => fake()->randomElement(['draft', 'sent', 'closed', 'cancelled']),
            'description' => fake()->text(),
            'required_by_date' => fake()->dateTime('+30 days'),
            'issued_date' => fake()->dateTime(),
            'deadline_date' => fake()->dateTime('+45 days'),
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
