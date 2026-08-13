<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\PaymentSchedule;

class PaymentScheduleFactory extends Factory
{
    protected $model = PaymentSchedule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'invoice_id' => fake()->word(),
            'due_date' => fake()->dateTime(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'currency' => fake()->word(),
            'payment_method' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'reminder_sent_at' => fake()->word(),
            'calendar_event_id' => fake()->word(),
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