<?php

namespace Modules\Security\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\TrustZone;

class TrustZoneFactory extends Factory
{
    protected $model = TrustZone::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'zone_name' => fake()->words(2, true) . ' Zone',
            'zone_type' => fake()->randomElement(['internal', 'dmz', 'external', 'restricted']),
            'description' => fake()->text(),
            'cidr_blocks' => [fake()->ipv4() . '/24', fake()->ipv4() . '/24'],
            'device_policies' => ['require_mdm' => fake()->boolean(), 'min_os_version' => '14.0'],
            'authentication_policies' => ['mfa_required' => fake()->boolean(), 'max_session_minutes' => fake()->numberBetween(15, 120)],
            'trust_score_minimum' => fake()->numberBetween(0, 100),
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
        // TrustZone uses SoftDeletes — 'archived' maps to a real deleted_at
        // rather than the nonexistent archived_at column.
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
