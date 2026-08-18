<?php

declare(strict_types=1);

namespace Modules\Inventory\Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Chantier 8.3: InventoryWebController's 8 methods (suppliers, purchaseOrders,
 * picking, cycleCounts, shipments, returns, crossdock, waves) were real —
 * correct props matching their pages' defineProps exactly — but had zero
 * routes anywhere. 4 of the 8 target pages (Shipments, Returns, WMS/Crossdock,
 * WMS/Waves) turned out to be fully self-contained axios-fetch pages that
 * never read server props at all, so those were routed via a plain
 * Inertia::render() instead of running the controller's real (but wasted)
 * queries against them. ChannelController (marketplace channel connections)
 * was real and routed at the API layer with no page at all — new
 * self-contained page.
 */
class Chantier83InventoryOrphanedScreensWebTest extends TestCase
{
    public function test_suppliers_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/suppliers');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/Suppliers/Index', false)
            ->has('suppliers')
            ->has('filters'));
    }

    public function test_purchase_orders_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/purchase-orders');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/PurchaseOrders/Index', false)
            ->has('orders')
            ->has('suppliers')
            ->has('warehouses')
            ->has('filters'));
    }

    public function test_wms_picking_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/wms/picking');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/WMS/Picking', false)
            ->has('orders')
            ->has('warehouses')
            ->has('filters'));
    }

    public function test_cycle_counts_page_renders()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/cycle-counts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/CycleCounts/Index', false)
            ->has('counts')
            ->has('warehouses')
            ->has('filters'));
    }

    public function test_shipments_page_renders_with_no_server_props()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/shipments');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/Shipments/Index', false));
    }

    public function test_returns_page_renders_with_no_server_props()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/returns');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/Returns/Index', false));
    }

    public function test_wms_crossdock_page_renders_with_no_server_props()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/wms/crossdock');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/WMS/Crossdock/Index', false));
    }

    public function test_wms_waves_page_renders_with_no_server_props()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/wms/waves');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/WMS/Waves/Index', false));
    }

    public function test_channels_page_renders_with_no_server_props()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/channels');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inventory/Channels/Index', false));
    }

    public function test_unauthenticated_users_are_redirected_from_all_nine()
    {
        foreach ([
            '/inventory/suppliers', '/inventory/purchase-orders', '/inventory/wms/picking',
            '/inventory/cycle-counts', '/inventory/shipments', '/inventory/returns',
            '/inventory/wms/crossdock', '/inventory/wms/waves', '/inventory/channels',
        ] as $url) {
            $this->get($url)->assertRedirect();
        }
    }
}
