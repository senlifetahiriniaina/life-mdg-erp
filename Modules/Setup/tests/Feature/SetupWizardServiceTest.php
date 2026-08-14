<?php

namespace Modules\Setup\Tests\Feature;

use App\Models\User;
use Modules\Setup\Models\CompanyProfile;
use Modules\Setup\Services\SetupWizardService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SetupWizardService used to persist every step only in the cache
 * (Cache::put, 24h TTL, lost on flush/restart). It now persists on
 * CompanyProfile — these tests walk all 6 steps against real storage.
 */
class SetupWizardServiceTest extends TestCase
{
    private SetupWizardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SetupWizardService::class);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_get_state_is_empty_shaped_before_any_step()
    {
        $state = $this->service->getState('t1');

        $this->assertSame(0, $state['step']);
        $this->assertNull($state['company']);
        $this->assertFalse($state['completed']);
    }

    public function test_save_company_persists_to_the_database()
    {
        $this->service->saveCompany('t1', ['company_name' => 'Life MDG', 'country_code' => 'MG']);

        $this->assertDatabaseHas('setup_company_profiles', [
            'tenant_id' => 't1',
            'company_name' => 'Life MDG',
        ]);

        $state = $this->service->getState('t1');
        $this->assertSame(1, $state['step']);
        $this->assertSame('Life MDG', $state['company']['company_name']);
    }

    public function test_save_admin_updates_user_profile_and_grants_admin_role()
    {
        $this->service->saveCompany('t1', ['company_name' => 'Life MDG', 'country_code' => 'MG']);
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->service->saveAdmin('t1', ['name' => 'New Name', 'locale' => 'fr'], $user->id);

        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertSame('New Name', $user->fresh()->name);

        $state = $this->service->getState('t1');
        $this->assertSame(2, $state['step']);
    }

    public function test_save_admin_does_not_duplicate_role_assignment_if_already_admin()
    {
        $this->service->saveCompany('t1', ['company_name' => 'Life MDG', 'country_code' => 'MG']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->service->saveAdmin('t1', ['name' => 'Whoever'], $user->id);

        $this->assertCount(1, $user->fresh()->roles);
    }

    public function test_save_admin_without_company_step_throws()
    {
        $this->expectException(\RuntimeException::class);
        $this->service->saveAdmin('t1', ['name' => 'X'], 1);
    }

    public function test_full_wizard_walkthrough_and_complete()
    {
        $this->service->saveCompany('t1', ['company_name' => 'Life MDG', 'country_code' => 'MG']);
        $user = User::factory()->create();
        $this->service->saveAdmin('t1', ['name' => 'Admin'], $user->id);
        $this->service->saveModules('t1', ['Core', 'Accounting']);
        $this->service->saveWorkflows('t1', ['approval_required' => true]);
        $this->service->saveApps('t1', ['webapp' => true, 'api' => true]);

        $state = $this->service->getState('t1');
        $this->assertSame(5, $state['step']);
        $this->assertSame(['Core', 'Accounting'], $state['modules']);

        $result = $this->service->complete('t1');

        $this->assertTrue($result['onboarding_completed']);
        $this->assertNotNull(CompanyProfile::where('tenant_id', 't1')->first()->onboarding_completed_at);

        $state = $this->service->getState('t1');
        $this->assertSame(6, $state['step']);
        $this->assertTrue($state['completed']);
    }

    public function test_complete_without_company_step_throws()
    {
        $this->expectException(\RuntimeException::class);
        $this->service->complete('nonexistent-tenant');
    }
}
