<?php

namespace Modules\Setup\Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * HTTP-level smoke test for the 6-step wizard API — SetupWizardService's
 * cache-to-database rewrite kept the same method signatures, this confirms
 * the controller wiring survived the change end-to-end.
 *
 * Chantier 32.10 (deep 14-layer audit): the wizard `POST` endpoints now
 * correctly require `module:Setup`+`role:employee,admin,super-admin` (a
 * real, previously-undocumented RBAC hole this chantier closed — see
 * routes/api.php's own docblock). Both the `role:` middleware and
 * `module:Setup` (a tenant-module-enabled check, not a permission check —
 * see `Modules\Core\Services\ModuleManager::isEnabled()`, which treats a
 * fresh tenant with zero `tenant_modules` rows as "everything enabled") only
 * need a real assigned Role, not a full permission seed — this file's own
 * pre-existing `Role::firstOrCreate()` pattern was kept, with the one real
 * bug it had fixed: it created the `admin` Role row but never actually
 * assigned it to the test user, so `role:` always denied regardless of the
 * RBAC fix.
 */
class SetupWizardControllerTest extends TestCase
{
    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_full_wizard_flow_over_http()
    {
        $user = $this->adminUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/company', [
                'company_name' => 'Life MDG',
                'country_code' => 'MG',
            ])->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/admin', ['name' => 'Admin User'])
            ->assertOk();

        $this->assertTrue($user->fresh()->hasRole('admin'));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/modules', ['modules' => ['Core', 'HR']])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/workflows', ['approval_required' => true])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/apps', ['apps' => ['webapp' => true, 'api' => true]])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/complete')
            ->assertOk()
            ->assertJsonPath('data.onboarding_completed', true);

        $state = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/setup/wizard/state')
            ->assertOk()
            ->json('data');

        $this->assertSame(6, $state['step']);
        $this->assertTrue($state['completed']);
    }

    public function test_complete_before_company_step_returns_422()
    {
        $user = $this->adminUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/complete')
            ->assertStatus(422);
    }

    /**
     * Chantier 32.10: real regression lock for the RBAC hole itself — a
     * plain, roleless authenticated user must now be denied, where before
     * this fix they were not.
     */
    public function test_wizard_denies_a_user_with_no_role_at_all()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/company', [
                'company_name' => 'Rogue Co',
                'country_code' => 'MG',
            ])->assertForbidden();
    }
}
