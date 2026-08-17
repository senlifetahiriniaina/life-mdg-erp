<?php

declare(strict_types=1);

namespace Modules\Logistics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Originally written against the unrouted `WarehouseShipmentController`
 * (dead parallel `wh_*`/`lgx_*` stack — Warehouse/LgxShipment/LgxCarrier —
 * that is deliberately NOT wired into routes/api.php because it collides
 * with the already-active ShipmentController/CarrierController on the same
 * URL paths with different models; see the routing note above the
 * "Phase 47" route group in Modules/Logistics/routes/api.php).
 *
 * Rewritten to exercise the equivalent business scenarios against the real,
 * routed owners: warehouses are owned by Inventory
 * (`/api/v1/inventory/warehouses`), shipments/carriers by the real Logistics
 * ShipmentController/CarrierController, and shipment-level KPIs by
 * LogisticsAnalyticsController (`/api/v1/logistics/analytics/kpis`) — the
 * only one of the four scenarios with no other test coverage.
 */
class WarehouseShipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_warehouses_returns_ok(): void
    {
        $this->actingAsUser('admin');

        $response = $this->getJson('/api/v1/inventory/warehouses');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_shipments_returns_ok(): void
    {
        $this->actingAsUser('logistics-manager');

        $response = $this->getJson('/api/v1/logistics/shipments');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_carriers_returns_ok(): void
    {
        $this->actingAsUser('logistics-manager');

        $response = $this->getJson('/api/v1/logistics/carriers');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_get_shipment_kpis_returns_ok(): void
    {
        $this->actingAsUser('logistics-manager');

        $response = $this->getJson('/api/v1/logistics/analytics/kpis');
        $response->assertStatus(200)->assertJsonStructure(['total_shipments', 'delivered', 'in_transit']);
    }
}
