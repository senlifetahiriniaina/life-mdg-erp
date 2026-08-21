<?php

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantAuditLog;

/**
 * Chantier 32.1: this was scaffold boilerplate — every field name (name/
 * title/slug/email/amount/quantity/…) is unrelated to this model's real
 * $guarded=[] columns (tenant_id/user_id/action/entity_type/entity_id/
 * old_values/new_values/ip_address/user_agent). Rewritten to match the
 * real tenant_audit_log table.
 */
class TenantAuditLogFactory extends Factory
{
    protected $model = TenantAuditLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['provisioned', 'suspended', 'reactivated', 'plan_upgraded', 'purged']),
            'entity_type' => 'tenant',
            'entity_id' => fake()->uuid(),
            'old_values' => [],
            'new_values' => [],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
