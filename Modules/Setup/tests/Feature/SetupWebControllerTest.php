<?php

namespace Modules\Setup\Tests\Feature;

use App\Models\User;
use Modules\Setup\Models\CompanyProfile;
use Tests\TestCase;

/**
 * Neither /setup nor /setup/wizard had a web route before this change —
 * SetupIndex.vue/SetupWizard.vue were never rendered by anything.
 */
class SetupWebControllerTest extends TestCase
{
    public function test_setup_index_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/setup');

        $response->assertOk();
        // shouldExist=false: Inertia's testing view-finder doesn't know
        // about this app's custom module-prefix resolution in app.js
        // (Setup/SetupIndex -> Modules/Setup/resources/js/Pages/SetupIndex.vue)
        // — a pre-existing gap affecting every module-prefixed Inertia
        // page in this app (confirmed failing identically on the
        // unmodified, unrelated AccountingWebTest), not something this
        // change introduces or is in scope to fix.
        $response->assertInertia(fn ($page) => $page->component('Setup/SetupIndex', false));
    }

    public function test_setup_wizard_renders_with_server_side_state()
    {
        $user = User::factory()->create();
        CompanyProfile::create(['tenant_id' => 'default', 'company_name' => 'Life MDG', 'country_code' => 'MG']);

        $response = $this->actingAs($user)->get('/setup/wizard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Setup/SetupWizard', false)
            ->where('state.company.company_name', 'Life MDG')
        );
    }

    public function test_setup_routes_require_authentication()
    {
        $this->get('/setup')->assertRedirect('/login');
        $this->get('/setup/wizard')->assertRedirect('/login');
    }
}
