<?php

namespace Modules\Setup\Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * HTTP-level smoke test for the 6-step wizard API — SetupWizardService's
 * cache-to-database rewrite kept the same method signatures, this confirms
 * the controller wiring survived the change end-to-end.
 */
class SetupWizardControllerTest extends TestCase
{
    public function test_full_wizard_flow_over_http()
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();

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
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/setup/wizard/complete')
            ->assertStatus(422);
    }
}
