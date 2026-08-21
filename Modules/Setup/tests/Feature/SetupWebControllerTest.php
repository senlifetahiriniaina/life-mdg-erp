<?php

namespace Modules\Setup\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Setup\Models\CompanyProfile;
use Tests\TestCase;

/**
 * Neither /setup nor /setup/wizard had a web route before this change —
 * SetupIndex.vue/SetupWizard.vue were never rendered by anything.
 *
 * Chantier 32.10 (deep 14-layer audit): `SetupWebController` was found
 * still reading `$request->user()->tenant_id ?? 'default'` — the
 * well-documented phantom `users.tenant_id` column already fixed
 * everywhere else in this module (see its own docblock) — meaning the
 * server-rendered initial page load silently collapsed every company's
 * onboarding state into one shared bucket. This file's own pre-existing
 * `test_setup_wizard_renders_with_server_side_state()` test asserted
 * exactly that buggy behavior (a `CompanyProfile` keyed on the literal
 * string `'default'`) — rewritten to use a real `company_id`, plus a new
 * cross-tenant regression test locking in the fix.
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
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        CompanyProfile::create(['tenant_id' => (string) $company->id, 'company_name' => 'Life MDG', 'country_code' => 'MG']);

        $response = $this->actingAs($user)->get('/setup/wizard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Setup/SetupWizard', false)
            ->where('state.company.company_name', 'Life MDG')
        );
    }

    /**
     * Chantier 32.10: real cross-tenant regression lock — company B's
     * admin visiting `/setup/wizard` must never see company A's
     * onboarding draft in the server-rendered initial prop.
     */
    public function test_setup_wizard_does_not_leak_another_companys_state()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userB = User::factory()->create(['company_id' => $companyB->id]);

        CompanyProfile::create(['tenant_id' => (string) $companyA->id, 'company_name' => 'Société A', 'country_code' => 'MG']);

        $response = $this->actingAs($userB)->get('/setup/wizard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Setup/SetupWizard', false)
            ->where('state.company', null)
        );
    }

    public function test_setup_routes_require_authentication()
    {
        $this->get('/setup')->assertRedirect('/login');
        $this->get('/setup/wizard')->assertRedirect('/login');
    }
}
