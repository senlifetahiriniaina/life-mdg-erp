<?php

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantUser;

/**
 * Chantier 32.1: this was scaffold boilerplate — tenant_id/user_id/role/
 * joined_at/invited_by all set via fake()->word() (strings on FK/enum/
 * datetime columns; joined_at in particular fataled Carbon's parser with a
 * garbage string the instant this factory was ever actually used, e.g.
 * "similique"), plus a long tail of fields (name/title/slug/email/amount/
 * quantity/…) that don't exist on this model at all. Rewritten to match
 * TenantUser's real $fillable/$casts.
 */
class TenantUserFactory extends Factory
{
    protected $model = TenantUser::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['owner', 'admin', 'user']),
            'joined_at' => now(),
            'invited_by' => null,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'owner']);
    }
}
