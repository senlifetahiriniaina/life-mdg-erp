<?php

namespace Modules\Logistics\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Models\{Shipment, Tracking, Carrier};
use Modules\Logistics\Services\LogisticsService;

class LogisticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private LogisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LogisticsService();
    }

    // Shipment Tracking (5 tests)
    public function test_create_shipment_tracking(): void
    {
        $shipment = Shipment::factory()->create();
        $tracking = $this->service->createTracking($shipment, 'TRACK123456');

        $this->assertNotNull($tracking->id);
        $this->assertEquals('TRACK123456', $tracking->tracking_number);
    }

    public function test_update_tracking_status(): void
    {
        $tracking = Tracking::factory()->create(['status' => 'pending']);
        $updated = $this->service->updateTrackingStatus($tracking, 'in_transit');

        $this->assertEquals('in_transit', $updated->status);
    }

    public function test_track_shipment_milestones(): void
    {
        $tracking = Tracking::factory()->create();

        $this->service->recordMilestone($tracking, 'picked_up');
        $this->service->recordMilestone($tracking, 'in_transit');
        $this->service->recordMilestone($tracking, 'delivered');

        $milestones = $this->service->getMilestones($tracking);
        $this->assertCount(3, $milestones);
    }

    public function test_estimated_delivery_calculation(): void
    {
        $shipment = Shipment::factory()->create();
        $estimated = $this->service->calculateEstimatedDelivery($shipment);

        $this->assertNotNull($estimated);
        $this->assertTrue($estimated > now());
    }

    public function test_shipment_delay_detection(): void
    {
        $tracking = Tracking::factory()->create([
            'estimated_delivery' => now()->subHours(2),
            'status' => 'in_transit'
        ]);

        $isDelayed = $this->service->isDelayed($tracking);
        $this->assertTrue($isDelayed);
    }

    // Carrier Integration (4 tests)
    public function test_sync_tracking_from_carrier_api(): void
    {
        $carrier = Carrier::factory()->create();
        $tracking = Tracking::factory()->create(['carrier_id' => $carrier->id]);

        $synced = $this->service->syncWithCarrier($tracking);
        $this->assertTrue($synced);
    }

    public function test_carrier_rate_calculation(): void
    {
        $carrier = Carrier::factory()->create();
        $weight = 5.5;
        $distance = 250;

        $rate = $this->service->calculateRate($carrier, $weight, $distance);

        $this->assertGreaterThan(0, $rate);
    }

    public function test_carrier_selection_optimization(): void
    {
        Carrier::factory()->count(3)->create();

        $shipment = Shipment::factory()->create(['weight' => 10, 'destination' => 'NYC']);
        $bestCarrier = $this->service->selectOptimalCarrier($shipment);

        $this->assertNotNull($bestCarrier);
    }

    public function test_carrier_capacity_availability_check(): void
    {
        $carrier = Carrier::factory()->create(['capacity' => 100, 'current_load' => 95]);

        $available = $this->service->hasCapacity($carrier, 10);
        $this->assertFalse($available);
    }

    // Customs Declaration (4 tests)
    public function test_create_customs_declaration(): void
    {
        $shipment = Shipment::factory()->create(['international' => true]);
        $declaration = $this->service->createCustomsDeclaration($shipment, [
            'items' => [
                ['description' => 'Electronics', 'value' => 500]
            ]
        ]);

        $this->assertNotNull($declaration->id);
    }

    public function test_customs_value_calculation(): void
    {
        $declaration = $this->service->createCustomsDeclaration(null, [
            'items' => [
                ['description' => 'Item 1', 'value' => 100, 'quantity' => 2],
                ['description' => 'Item 2', 'value' => 50, 'quantity' => 3]
            ]
        ]);

        $totalValue = $this->service->calculateDeclaredValue($declaration);
        $this->assertEquals(350, $totalValue); // (100*2) + (50*3)
    }

    public function test_customs_document_generation(): void
    {
        $declaration = $this->service->createCustomsDeclaration(null, [
            'items' => [['description' => 'Test', 'value' => 100]]
        ]);

        $document = $this->service->generateCustomsDocument($declaration);
        $this->assertNotNull($document);
    }

    public function test_duty_and_tax_estimation(): void
    {
        $declaration = $this->service->createCustomsDeclaration(null, [
            'destination' => 'FR',
            'items' => [['description' => 'Electronics', 'value' => 1000]]
        ]);

        $duties = $this->service->estimateDutiesAndTaxes($declaration);
        $this->assertGreaterThan(0, $duties);
    }

    // Route Optimization (4 tests)
    public function test_optimize_delivery_route(): void
    {
        $stops = [
            ['lat' => 40.7128, 'lng' => -74.0060], // NYC
            ['lat' => 40.7580, 'lng' => -73.9855], // NYC 2
            ['lat' => 40.7489, 'lng' => -73.9680]  // NYC 3
        ];

        $optimized = $this->service->optimizeRoute($stops);

        $this->assertCount(3, $optimized);
    }

    public function test_route_distance_calculation(): void
    {
        $start = ['lat' => 40.7128, 'lng' => -74.0060];
        $end = ['lat' => 34.0522, 'lng' => -118.2437];

        $distance = $this->service->calculateDistance($start, $end);

        $this->assertGreaterThan(0, $distance);
        $this->assertGreaterThan(2000, $distance); // Should be > 2000 km
    }

    public function test_eta_calculation_with_traffic(): void
    {
        $route = [
            ['lat' => 40.7128, 'lng' => -74.0060],
            ['lat' => 40.7580, 'lng' => -73.9855]
        ];

        $eta = $this->service->calculateETA($route, consider_traffic: true);
        $this->assertNotNull($eta);
    }

    public function test_geofencing_arrival_detection(): void
    {
        $shipment = Shipment::factory()->create([
            'destination_lat' => 40.7128,
            'destination_lng' => -74.0060
        ]);

        $currentLat = 40.7129; // Very close
        $currentLng = -74.0061;

        $arrived = $this->service->checkGeofenceArrival($shipment, $currentLat, $currentLng);
        $this->assertTrue($arrived);
    }

    // API Tests (2 tests)
    public function test_api_get_shipment_tracking(): void
    {
        $tracking = Tracking::factory()->create();

        $response = $this->getJson("/api/v1/logistics/tracking/{$tracking->tracking_number}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', $tracking->status);
    }

    public function test_api_list_shipments_filtered(): void
    {
        Shipment::factory()->count(20)->create(['status' => 'delivered']);
        Shipment::factory()->count(10)->create(['status' => 'in_transit']);

        $response = $this->getJson('/api/v1/logistics/shipments?status=delivered');

        $response->assertStatus(200);
        $response->assertJsonCount(20, 'data');
    }

    // Advanced Tests (1 test)
    public function test_batch_shipment_processing(): void
    {
        $shipments = Shipment::factory()->count(100)->create(['status' => 'pending']);

        $startTime = microtime(true);
        $this->service->processBatch($shipments);
        $duration = microtime(true) - $startTime;

        $this->assertLessThan(10, $duration); // Should process 100 in < 10 seconds
    }
}
