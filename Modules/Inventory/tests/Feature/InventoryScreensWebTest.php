<?php

declare(strict_types=1);

namespace Modules\Inventory\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Chantier 6: Stock/Movements.vue, ReorderAutomation/Index.vue,
 * DemandForecast/Index.vue and MarketplaceSync/Index.vue were all real,
 * axios-wired components with zero web route pointing at them (Inventory's
 * routes/web.php only registered products/categories/warehouses/
 * stock-adjustments). Routed here; Stock/Movements.vue's URL was also fixed
 * from the non-existent /api/v1/inventory/movements to the real
 * /api/v1/inventory/stock-movements, and ReorderAutomation/DemandForecast/
 * MarketplaceSync were rewritten off invented endpoints onto the real
 * low-stock/ai/suggest-reorder, demand-forecasts, and sync/ecommerce APIs.
 */
class InventoryScreensWebTest extends TestCase
{
    public function test_stock_movements_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/stock/movements');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/Stock/Movements', false));
    }

    public function test_reorder_automation_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/reorder-automation');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/ReorderAutomation/Index', false));
    }

    public function test_demand_forecast_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/demand-forecast');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/DemandForecast/Index', false));
    }

    public function test_marketplace_sync_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/marketplace-sync');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/MarketplaceSync/Index', false));
    }

    public function test_unauthenticated_users_are_redirected_from_all_four()
    {
        foreach (['/inventory/stock/movements', '/inventory/reorder-automation', '/inventory/demand-forecast', '/inventory/marketplace-sync'] as $url) {
            $this->get($url)->assertRedirect();
        }
    }

    /**
     * Chantier 8.3: /categories and /warehouses 500'd on every visit —
     * their Web controllers rendered Categories/Form.vue, Categories/Show.vue,
     * Warehouses/Form.vue, Warehouses/Show.vue, none of which existed anywhere
     * in the repo. Both are now self-contained list+modal-CRUD pages (same
     * pattern as the real Warehouses/Index.vue was already using) with the
     * dead create/show/edit web actions trimmed away.
     */
    public function test_categories_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/categories');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/Categories/Index', false));
    }

    public function test_warehouses_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/warehouses');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/Warehouses/Index', false));
    }

    /**
     * stock-adjustments was a fully redundant scaffold (Index/Form/Show.vue
     * never existed) — deleted rather than built, since Stock/Movements.vue
     * already covers manual adjustments via POST stock-movements with
     * type=adjustment.
     */
    public function test_stock_adjustments_route_no_longer_exists()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/stock-adjustments');

        $response->assertNotFound();
    }
}
