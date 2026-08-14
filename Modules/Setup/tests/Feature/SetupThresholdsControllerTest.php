<?php

namespace Modules\Setup\Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bridges the Setup wizard's "workflows" step to Achats/Accounting's
 * amount-threshold rules. Neither workflow exists until a real PO/invoice
 * is submitted, so this endpoint seeds it on demand (idempotent) rather
 * than showing an admin an empty screen on a fresh install.
 */
class SetupThresholdsControllerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_achats_thresholds_seed_on_first_visit()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/setup/wizard/thresholds/achats');

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Standard PO Approval');
        $this->assertNotEmpty($response->json('data.rules'));
    }

    public function test_accounting_thresholds_seed_on_first_visit()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/setup/wizard/thresholds/accounting');

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Invoice Approval (OHADA thresholds)');
        $this->assertCount(3, $response->json('data.rules'));
    }

    public function test_unknown_module_returns_404()
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/setup/wizard/thresholds/not-a-module')
            ->assertStatus(404);
    }

    public function test_non_admin_is_forbidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/setup/wizard/thresholds/achats')
            ->assertStatus(403);
    }
}
