<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Modules\Core\Models\Tenant;
use Tests\TestCase;

/**
 * TenantExchanges/Index.vue (built in an earlier chantier) and the new
 * Sandboxes/Index.vue both had no route in routes/web.php at all — the
 * only way to reach either was to type the URL by hand.
 */
class AdminSandboxesWebTest extends TestCase
{
    private function superAdmin(): User
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_sandboxes_page_renders_for_super_admin()
    {
        $user = $this->superAdmin();

        $response = $this->actingAs($user)->get('/admin/sandboxes');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Sandboxes/Index', false));
    }

    public function test_sandboxes_page_forbidden_for_non_admin()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/sandboxes');

        $response->assertForbidden();
    }

    public function test_exchanges_page_renders_for_super_admin()
    {
        $user = $this->superAdmin();

        $response = $this->actingAs($user)->get('/admin/exchanges');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/TenantExchanges/Index', false));
    }

    public function test_sandbox_full_lifecycle_via_api()
    {
        $user = $this->superAdmin();
        $tenant = Tenant::factory()->create();
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/v1/core/sandboxes', [
            'name' => 'Test migration Q3',
            'tenant_id' => $tenant->id,
        ]);
        $create->assertCreated();
        $sandboxId = $create->json('data.id');

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/core/sandboxes');
        $list->assertOk();
        $this->assertContains($sandboxId, collect($list->json('data'))->pluck('id')->all());

        $reset = $this->actingAs($user, 'sanctum')->postJson("/api/v1/core/sandboxes/{$sandboxId}/reset");
        $reset->assertOk();

        $delete = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/core/sandboxes/{$sandboxId}");
        $delete->assertOk();
    }
}
