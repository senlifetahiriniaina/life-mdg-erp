<?php

namespace Modules\Security\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\ComplianceControl;
use Modules\Security\Models\ComplianceViolation;

class ComplianceViolationFactory extends Factory
{
    protected $model = ComplianceViolation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'compliance_control_id' => ComplianceControl::factory(),
            'violation_type' => fake()->word(),
            // 'description' isn't in $fillable (the model was moved onto
            // 'violation_description' by the later patch migration below),
            // but the original security_compliance_violations.description
            // column is still NOT NULL with no default — see
            // 2026_08_16_000001_fix_security_compliance_violations_table.php,
            // which only adds columns and never drops it. Omitting it fails
            // every insert with a NOT NULL constraint violation.
            'description' => fake()->sentence(),
            'violation_description' => fake()->sentence(),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'violation_status' => fake()->randomElement(['open', 'remediated', 'waived', 'closed']),
            'detected_at' => fake()->dateTime(),
            'remediation_deadline' => fake()->dateTime('+30 days'),
            'remediated_at' => fake()->dateTime(),
            'remediation_notes' => fake()->sentence(),
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
