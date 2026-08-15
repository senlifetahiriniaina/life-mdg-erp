<?php

namespace Modules\Security\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\ComplianceControl;

class ComplianceControlFactory extends Factory
{
    protected $model = ComplianceControl::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'framework' => fake()->randomElement(['SOX', 'HIPAA', 'PCI-DSS', 'GDPR']),
            'control_id' => fake()->unique()->bothify('CTRL-###'),
            'control_name' => fake()->sentence(3),
            'control_description' => fake()->sentence(),
            'control_type' => fake()->randomElement(['preventive', 'detective', 'corrective']),
            'implementation_status' => fake()->randomElement(['not_started', 'planned', 'implemented', 'verified', 'failed']),
            'implementation_details' => ['owner' => fake()->name(), 'notes' => fake()->sentence()],
            'last_verified_at' => fake()->dateTime(),
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
