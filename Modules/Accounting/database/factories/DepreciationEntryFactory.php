<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\DepreciationEntry;

class DepreciationEntryFactory extends Factory
{
    protected $model = DepreciationEntry::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'depreciation_schedule_id' => fake()->word(),
            'period_date' => fake()->word(),
            'depreciation_amount' => fake()->word(),
            'accumulated_depreciation' => fake()->word(),
            'book_value' => fake()->word(),
            'journal_entry_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'recorded_at' => fake()->word(),
            'notes' => fake()->text(),
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