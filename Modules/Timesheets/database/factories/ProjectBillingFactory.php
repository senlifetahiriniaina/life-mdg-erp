<?php

namespace Modules\Timesheets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Timesheets\Models\ProjectBilling;

class ProjectBillingFactory extends Factory
{
    protected $model = ProjectBilling::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'project_id' => fake()->word(),
            'reference' => fake()->bothify('??-##'),
            'billing_type' => fake()->word(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'tva_amount' => fake()->word(),
            'total_ttc' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'ohada_account' => fake()->word(),
            'description' => fake()->text(),
            'billing_date' => fake()->word(),
            'milestone_id' => fake()->word(),
            'percentage' => fake()->numberBetween(0, 100),
            'period_start' => fake()->word(),
            'period_end' => fake()->word(),
            'invoice_reference' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'notes' => fake()->text(),
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
            'archived_at' => now(),
        ]);
    }
}