<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;
use Modules\Core\Models\TenantModule;

/**
 * Chantier 32.1: this was scaffold boilerplate — tenant_id/enabled/settings
 * all set via fake()->word() (strings on FK/boolean/array columns — a
 * guaranteed cast failure on 'enabled' the instant this factory was ever
 * actually used). Rewritten to match TenantModule's real $fillable/$casts.
 */
class TenantModuleFactory extends Factory
{
    protected $model = TenantModule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'module' => fake()->randomElement(['Accounting', 'CRM', 'Sales', 'Inventory', 'HR']),
            'enabled' => true,
            'department' => null,
            'settings' => [],
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }
}
