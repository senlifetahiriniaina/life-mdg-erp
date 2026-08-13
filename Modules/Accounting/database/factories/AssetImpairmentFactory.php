<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\AssetImpairment;

class AssetImpairmentFactory extends Factory
{
    protected $model = AssetImpairment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'fixed_asset_id' => fake()->word(),
            'impairment_date' => fake()->word(),
            'original_cost' => fake()->word(),
            'accumulated_depreciation_before' => fake()->word(),
            'book_value_before' => fake()->word(),
            'fair_value' => fake()->word(),
            'impairment_loss' => fake()->word(),
            'new_book_value' => fake()->word(),
            'impairment_reason' => fake()->word(),
            'journal_entry_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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