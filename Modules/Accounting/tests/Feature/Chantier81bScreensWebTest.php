<?php

declare(strict_types=1);

namespace Modules\Accounting\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Chantier 8.1b: routes for the 7 previously-unrouted Accounting controllers
 * (AssetImpairment, BudgetVariance, CostEngine, DepreciationPolicy,
 * DepreciationSchedule, IntercompanyClearance, ScenarioPlanning). Every page
 * fetches its own data client-side (no server-side props), so this just
 * verifies the routes are reachable and render the right component.
 */
class Chantier81bScreensWebTest extends TestCase
{
    public function test_asset_impairments_renders(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/accounting/asset-impairments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/AssetImpairments/Index', false));
    }

    public function test_budget_variance_renders(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/accounting/budget-variance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/BudgetVariance/Index', false));
    }

    public function test_cost_engine_renders(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/accounting/cost-engine');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/CostEngine/Index', false));
    }

    public function test_depreciation_policies_renders(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/accounting/depreciation-policies');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/DepreciationPolicies/Index', false));
    }

    public function test_depreciation_schedules_renders(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/accounting/depreciation-schedules');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/DepreciationSchedules/Index', false));
    }

    public function test_intercompany_clearances_renders(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/accounting/intercompany-clearances');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/IntercompanyClearances/Index', false));
    }

    public function test_scenario_planning_renders(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/accounting/scenario-planning');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Accounting/ScenarioPlanning/Index', false));
    }
}
