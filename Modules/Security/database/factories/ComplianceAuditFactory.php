<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\ComplianceAudit;

class ComplianceAuditFactory extends Factory
{
    protected $model = ComplianceAudit::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'audit_type' => fake()->word(),
            'framework' => fake()->word(),
            'audit_start_date' => fake()->word(),
            'audit_end_date' => fake()->word(),
            'controls_evaluated' => fake()->word(),
            'controls_compliant' => fake()->word(),
            'controls_non_compliant' => fake()->word(),
            'compliance_score' => fake()->word(),
            'findings' => fake()->word(),
            'audit_status' => fake()->word(),
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