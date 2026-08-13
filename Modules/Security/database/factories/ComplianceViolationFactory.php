<?php

namespace Modules\Security\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\app\Models\ComplianceViolation;

class ComplianceViolationFactory extends Factory
{
    protected $model = ComplianceViolation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'compliance_control_id' => fake()->word(),
            'violation_type' => fake()->word(),
            'violation_description' => fake()->word(),
            'severity' => fake()->word(),
            'violation_status' => fake()->word(),
            'detected_at' => fake()->word(),
            'remediation_deadline' => fake()->word(),
            'remediated_at' => fake()->word(),
            'remediation_notes' => fake()->word(),
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