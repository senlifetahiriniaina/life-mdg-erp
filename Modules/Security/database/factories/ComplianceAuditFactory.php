<?php

namespace Modules\Security\Database\Factories;

use App\Models\Company;
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
            'company_id' => Company::factory(),
            'audit_type' => fake()->randomElement(['scheduled', 'on_demand', 'incident_response']),
            'framework' => fake()->randomElement(['SOX', 'HIPAA', 'PCI-DSS', 'GDPR']),
            'audit_start_date' => fake()->dateTime(),
            'audit_end_date' => fake()->dateTime(),
            'controls_evaluated' => fake()->numberBetween(0, 100),
            'controls_compliant' => fake()->numberBetween(0, 100),
            'controls_non_compliant' => fake()->numberBetween(0, 20),
            'compliance_score' => fake()->randomFloat(2, 0, 100),
            'findings' => ['summary' => fake()->sentence(), 'issues' => fake()->words(3)],
            'audit_status' => fake()->randomElement(['in_progress', 'completed']),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        // No is_active column on this model; kept as a no-op state so
        // existing callers of ->inactive() don't break.
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        // No archived_at column on this model; kept as a no-op state so
        // existing callers of ->archived() don't break.
        return $this->state(fn (array $attributes) => []);
    }
}
