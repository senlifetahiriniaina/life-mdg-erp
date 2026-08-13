<?php

declare(strict_types=1);

namespace Modules\Logistics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseShipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_warehouses_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/logistics/warehouses');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_shipments_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/logistics/shipments');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_carriers_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/logistics/carriers');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_get_shipment_kpis_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/logistics/shipments/kpis');
        $response->assertStatus(200);
    }
}
