<?php

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantInvitation;

/**
 * Chantier 32.1: this was scaffold boilerplate — expires_at/accepted_at set
 * via fake()->word() on datetime-cast columns, tenant_id/invited_by set via
 * fake()->word() on FK columns, plus a long tail of fields (name/title/
 * slug/…) that don't exist on this model at all. Rewritten to match
 * TenantInvitation's real $fillable/$casts.
 */
class TenantInvitationFactory extends Factory
{
    protected $model = TenantInvitation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(['admin', 'user']),
            'token' => fake()->uuid(),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['accepted_at' => now()]);
    }
}
