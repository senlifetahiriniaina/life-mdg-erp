<?php

namespace Modules\Security\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\app\Models\ComplianceControl;

class ComplianceControlFactory extends Factory
{
    protected $model = ComplianceControl::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'framework' => fake()->word(),
            'control_id' => fake()->word(),
            'control_name' => fake()->word(),
            'control_description' => fake()->word(),
            'control_type' => fake()->word(),
            'implementation_status' => fake()->word(),
            'implementation_details' => fake()->word(),
            'last_verified_at' => fake()->word(),
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