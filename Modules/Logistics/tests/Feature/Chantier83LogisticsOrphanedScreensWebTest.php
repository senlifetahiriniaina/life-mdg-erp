<?php

declare(strict_types=1);

namespace Modules\Logistics\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Chantier 8.3: RouteOptimizationController's optimize()/result() endpoints
 * were real and already routed at the API layer, and RouteOptimization/Index.vue
 * is a real self-fetch page (axios calls to /api/v1/logistics/routes/optimize
 * and /api/v1/logistics/routes/optimize/{jobId}/result) with zero web route
 * pointing at it — routed via a plain Inertia::render(), same pattern as
 * Inventory's self-fetch pages.
 *
 * AIRiskMonitor/Index.vue and Returns/Index.vue were investigated too, but
 * both are 100% hardcoded mock data (no defineProps, no axios/fetch calls
 * anywhere) with no backing service of any kind — wiring them up would mean
 * inventing an entire AI supply-chain risk-scoring engine and a parallel
 * reverse-logistics/RMA workflow from scratch, not just adding a route. Left
 * unrouted pending a product decision (see CLAUDE.md Known gaps).
 */
class Chantier83LogisticsOrphanedScreensWebTest extends TestCase
{
    public function test_route_optimization_page_renders_with_no_server_props()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logistics/route-optimization');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Logistics/RouteOptimization/Index', false));
    }

    public function test_unauthenticated_users_are_redirected()
    {
        $this->get('/logistics/route-optimization')->assertRedirect();
    }
}
