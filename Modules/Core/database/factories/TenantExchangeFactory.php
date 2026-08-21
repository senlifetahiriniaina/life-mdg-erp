<?php

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantExchange;

/**
 * Chantier 32.1: this was scaffold boilerplate — source_tenant_id/
 * target_tenant_id/created_by_user_id/accepted_by_user_id/expires_at/
 * accepted_at all set via fake()->word() on FK/datetime columns. Rewritten
 * to match TenantExchange's real $fillable/$casts.
 */
class TenantExchangeFactory extends Factory
{
    protected $model = TenantExchange::class;

    public function definition(): array
    {
        return [
            'source_tenant_id' => Tenant::factory(),
            'target_tenant_id' => Tenant::factory(),
            'exchange_type' => fake()->randomElement(['data_share', 'partnership']),
            'payload' => ['note' => fake()->sentence()],
            'status' => 'pending',
            'message' => fake()->sentence(),
            'rejection_reason' => null,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'created_by_user_id' => User::factory(),
            'accepted_by_user_id' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }
}
