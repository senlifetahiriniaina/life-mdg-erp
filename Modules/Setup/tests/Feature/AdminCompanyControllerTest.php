<?php

namespace Modules\Setup\Tests\Feature;

use App\Models\User;
use Modules\Setup\Models\CompanyProfile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * AdminCompanyController::show()/update()/uploadLogo() all referenced
 * Modules\Setup\Models\CompanyProfile, which never existed — every call
 * fataled with a class-not-found error. CompanyProfile now exists
 * (2026_08_14_000011_create_setup_company_profiles_table.php); these
 * tests exercise the previously-guaranteed-fatal endpoints for real.
 */
class AdminCompanyControllerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_show_returns_null_when_no_profile_exists_yet()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/setup/v1/admin/company');

        $response->assertOk()->assertJson(['success' => true, 'data' => null]);
    }

    public function test_update_creates_a_profile_on_first_call()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/setup/v1/admin/company', [
                'company_name' => 'Life MDG',
                'country_code' => 'MG',
                'currency_code' => 'MGA',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.company_name', 'Life MDG')
            ->assertJsonPath('data.country_code', 'MG');

        $this->assertDatabaseHas('setup_company_profiles', [
            'company_name' => 'Life MDG',
            'country_code' => 'MG',
        ]);
    }

    public function test_update_edits_an_existing_profile()
    {
        CompanyProfile::create([
            'tenant_id' => 'default',
            'company_name' => 'Old Name',
            'country_code' => 'SN',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/setup/v1/admin/company', [
                'company_name' => 'New Name',
            ]);

        $response->assertOk()->assertJsonPath('data.company_name', 'New Name');
        $this->assertEquals(1, CompanyProfile::count());
    }
}
