<?php

namespace Modules\Accounting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\app\Models\DepreciationPolicy;

class DepreciationPolicyFactory extends Factory
{
    protected $model = DepreciationPolicy::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'policy_name' => fake()->word(),
            'asset_category' => fake()->word(),
            'depreciation_method' => fake()->word(),
            'default_useful_life_years' => fake()->word(),
            'default_residual_percentage' => fake()->word(),
            'tax_depreciation_method' => fake()->word(),
            'tax_useful_life_years' => fake()->word(),
            'policy_description' => fake()->word(),
            'is_active' => true,
            'effective_from' => fake()->word(),
            'effective_to' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
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