<?php

declare(strict_types=1);

namespace Modules\Security\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Chantier 6: Security/Index.vue existed but had no route anywhere
 * (RouteServiceProvider::map() only ever called mapApiRoutes()). Routed
 * at /security; its dashboard stats now come from GET /security/summary
 * (Chantier 32.3, see SecurityDashboardController) with the incidents/
 * threat-indicators lists still fetched separately for the page's 2 list
 * panels.
 *
 * Chantier 32.3: this route had no role: gate at all until this chantier —
 * test_index_renders_for_an_authenticated_user originally asserted 200 for
 * a bare, unroled User::factory(), which is exactly the RBAC hole the
 * chantier closed; updated to assign security-admin (the module's real
 * intended audience) and a new negative test locks in that a plain employee
 * is correctly denied.
 */
class SecurityScreensWebTest extends TestCase
{
    private function seedGuard(): void
    {
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
    }

    public function test_index_renders_for_a_security_admin()
    {
        $this->seedGuard();
        $user = User::factory()->create();
        $user->assignRole('security-admin');

        $response = $this->actingAs($user)->get('/security');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Security/Index', false));
    }

    public function test_index_is_forbidden_for_a_plain_employee()
    {
        $this->seedGuard();
        $user = User::factory()->create();
        $user->assignRole('employee');

        $response = $this->actingAs($user)->get('/security');

        $response->assertForbidden();
    }

    public function test_index_is_unreachable_when_not_authenticated()
    {
        $response = $this->get('/security');

        $response->assertRedirect();
    }
}
