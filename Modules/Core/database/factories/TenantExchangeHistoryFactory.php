<?php

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantExchange;
use Modules\Core\Models\TenantExchangeHistory;

/**
 * Chantier 32.1: this was scaffold boilerplate — exchange_id/actor_tenant_id/
 * actor_user_id set via fake()->word() on FK columns, plus 'notes' (not a
 * real fillable field on this model — the real field is 'note'). Rewritten
 * to match TenantExchangeHistory's real $fillable.
 */
class TenantExchangeHistoryFactory extends Factory
{
    protected $model = TenantExchangeHistory::class;

    public function definition(): array
    {
        return [
            'exchange_id' => TenantExchange::factory(),
            'action' => fake()->randomElement(['created', 'accepted', 'rejected', 'cancelled']),
            'actor_tenant_id' => Tenant::factory(),
            'actor_user_id' => User::factory(),
            'note' => fake()->sentence(),
        ];
    }
}
