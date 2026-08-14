<?php

namespace Modules\Setup\Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * HTTP-level smoke test for the module-activation admin endpoints, now
 * that ModuleManagerService is real. Snapshots/restores the real
 * config/modules_statuses.json around every test, same discipline as
 * ModuleManagerServiceTest.
 */
class AdminModulesControllerTest extends TestCase
{
    private string $statusesFile;

    private string $originalContents;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statusesFile = config('modules.activators.file.statuses-file');
        $this->originalContents = file_get_contents($this->statusesFile);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    protected function tearDown(): void
    {
        file_put_contents($this->statusesFile, $this->originalContents);
        parent::tearDown();
    }

    public function test_index_lists_modules()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/setup/v1/admin/modules');

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('HR', $names);
    }

    public function test_deactivating_a_required_module_returns_422_with_dependents()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/setup/v1/admin/modules/HR', ['is_active' => false]);

        $response->assertStatus(422);
        $this->assertContains('Payroll', $response->json('dependents'));
    }

    public function test_toggling_an_unknown_module_returns_404()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/setup/v1/admin/modules/NotARealModule', ['is_active' => true]);

        $response->assertStatus(404);
    }

    public function test_deactivate_then_reactivate_a_leaf_module()
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/setup/v1/admin/modules/Timesheets', ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/setup/v1/admin/modules/Timesheets', ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }
}
