<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * User::requiresTwoFactor() reads config('security.mandatory_2fa_roles')
 * instead of a hardcoded ['super-admin', 'admin'] array, so a role can be
 * added without a deploy. Confirms the config default keeps today's
 * behavior unchanged, and that a config-added role is actually gated.
 */
class RequiresTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_config_requires_2fa_for_admin_and_super_admin_only(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'hr-manager', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $hrManager = User::factory()->create();
        $hrManager->assignRole('hr-manager');

        $this->assertTrue($admin->requiresTwoFactor());
        $this->assertTrue($superAdmin->requiresTwoFactor());
        $this->assertFalse($hrManager->requiresTwoFactor());
    }

    public function test_a_role_added_to_config_is_gated_without_code_changes(): void
    {
        config(['security.mandatory_2fa_roles' => ['super-admin', 'admin', 'hr-manager']]);

        Role::firstOrCreate(['name' => 'hr-manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sales-rep', 'guard_name' => 'web']);

        $hrManager = User::factory()->create();
        $hrManager->assignRole('hr-manager');

        $salesRep = User::factory()->create();
        $salesRep->assignRole('sales-rep');

        $this->assertTrue($hrManager->requiresTwoFactor());
        $this->assertFalse($salesRep->requiresTwoFactor());
    }
}
