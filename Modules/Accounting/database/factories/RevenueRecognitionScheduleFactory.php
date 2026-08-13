<?php

namespace Modules\Accounting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\app\Models\RevenueRecognitionSchedule;

class RevenueRecognitionScheduleFactory extends Factory
{
    protected $model = RevenueRecognitionSchedule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'revenue_contract_id' => fake()->word(),
            'recognition_date' => fake()->word(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'tax_amount' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'journal_entry_id' => fake()->word(),
            'gl_account_id' => fake()->word(),
            'description' => fake()->text(),
            'recognized_at' => fake()->word(),
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