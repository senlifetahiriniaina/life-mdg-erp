<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\InvoiceApproval;

class InvoiceApprovalFactory extends Factory
{
    protected $model = InvoiceApproval::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'invoice_id' => fake()->word(),
            'invoice_type' => fake()->word(),
            'invoice_number' => fake()->word(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'currency' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'submitted_by' => fake()->word(),
            'submitted_at' => fake()->word(),
            'approval_chain' => fake()->word(),
            'current_level' => fake()->word(),
            'rejection_reason' => fake()->word(),
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