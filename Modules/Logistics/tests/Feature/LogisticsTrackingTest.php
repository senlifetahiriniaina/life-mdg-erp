<?php

declare(strict_types=1);

namespace Modules\Logistics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CarrierRate;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\TrackingEvent;
use Tests\TestCase;

/**
 * Originally written against a `Modules\Logistics\Services\LogisticsService`
 * god-object and a `Modules\Logistics\Models\Tracking` model — neither class
 * was ever written anywhere in this codebase (confirmed by grep: only this
 * test file referenced them), so every test errored on class-not-found.
 *
 * The capabilities this file exercised already exist in the real, decomposed
 * architecture and are (re)targeted here:
 *  - shipment creation/status/list-filter/customs-declaration/carrier CRUD are
 *    already fully covered by ShipmentApiTest, CarrierApiTest and
 *    CustomsDeclarationApiTest — not duplicated in this file.
 *  - route optimization is already fully covered by VrpRouteOptimizerTest
 *    (VRP endpoint) and Modules/Logistics/tests/Feature/RouteOptimizationTest.php
 *    (Haversine helper) — not duplicated here either.
 *  - tracking-event creation/history and carrier rate selection had NO real
 *    test coverage anywhere despite being real, working, routed code — these
 *    are the genuinely non-redundant scenarios kept below, rewritten against
 *    the real services: `TrackingEventController`/`Shipment::trackingEvents()`
 *    and `CarrierSelectionService` (via `POST logistics/carriers/select`).
 *  - "ETA with traffic", "geofencing arrival detection" and "batch shipment
 *    processing" (performance test) were invented capabilities with no real
 *    equivalent anywhere in the app (confirmed by grep for
 *    geofence/ETA/processBatch across Modules/Logistics) — dropped rather
 *    than rewritten, since building them would mean writing new business
 *    logic, which is out of scope for this test-only fix.
 */
class LogisticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUser('logistics-manager');
    }

    // ── Tracking events (real: TrackingEventController + Shipment::trackingEvents()) ──

    public function test_creates_a_tracking_event_and_updates_shipment_status(): void
    {
        $shipment = Shipment::factory()->create(['status' => 'booked']);

        $response = $this->postJson("/api/v1/logistics/shipments/{$shipment->id}/tracking-events", [
            'event_type' => 'picked_up',
            'status_detail' => 'Collected from shipper warehouse',
            'location_city' => 'Antananarivo',
            'location_country' => 'MG',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('event_type', 'picked_up');

        $this->assertDatabaseHas('logistics_shipments', [
            'id' => $shipment->id,
            'status' => 'picked_up',
        ]);
    }

    public function test_lists_tracking_history_for_a_shipment_in_chronological_order(): void
    {
        $shipment = Shipment::factory()->create();

        TrackingEvent::factory()->create([
            'shipment_id' => $shipment->id,
            'event_type' => 'delivered',
            'recorded_at' => now(),
        ]);
        TrackingEvent::factory()->create([
            'shipment_id' => $shipment->id,
            'event_type' => 'booked',
            'recorded_at' => now()->subDays(2),
        ]);
        TrackingEvent::factory()->create([
            'shipment_id' => $shipment->id,
            'event_type' => 'in_transit',
            'recorded_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/logistics/shipments/{$shipment->id}/tracking");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(3, $data);
        $this->assertSame(['booked', 'in_transit', 'delivered'], array_column($data, 'event_type'));
    }

    // ── Carrier rate calculation & selection (real: CarrierSelectionService) ──

    public function test_carrier_selection_ranks_options_cheapest_first_by_default(): void
    {
        $cheap = Carrier::factory()->create(['rating' => 3.0]);
        $pricey = Carrier::factory()->create(['rating' => 4.5]);

        CarrierRate::factory()->create([
            'carrier_id' => $cheap->id,
            'origin_country' => 'MG',
            'destination_country' => 'FR',
            'mode' => 'sea',
            'rate_type' => 'per_kg',
            'base_rate' => 2.0,
            'fuel_surcharge_pct' => 0,
            'min_charge' => 10,
            'transit_days' => 30,
        ]);
        CarrierRate::factory()->create([
            'carrier_id' => $pricey->id,
            'origin_country' => 'MG',
            'destination_country' => 'FR',
            'mode' => 'sea',
            'rate_type' => 'per_kg',
            'base_rate' => 20.0,
            'fuel_surcharge_pct' => 0,
            'min_charge' => 10,
            'transit_days' => 3,
        ]);

        $response = $this->postJson('/api/v1/logistics/carriers/select', [
            'origin_country' => 'MG',
            'destination_country' => 'FR',
            'transport_mode' => 'sea',
            'weight_kg' => 100,
            'priority' => 'cheapest',
        ]);

        $response->assertStatus(200);
        $options = $response->json('data');

        $this->assertCount(2, $options);
        $this->assertSame($cheap->id, $options[0]['carrier']['id']);
        $this->assertGreaterThan($options[0]['estimated_cost'], $options[1]['estimated_cost']);
    }

    public function test_carrier_selection_ranks_options_fastest_first_when_requested(): void
    {
        $slow = Carrier::factory()->create();
        $fast = Carrier::factory()->create();

        CarrierRate::factory()->create([
            'carrier_id' => $slow->id,
            'origin_country' => 'SN',
            'destination_country' => 'CI',
            'mode' => 'road',
            'transit_days' => 5,
        ]);
        CarrierRate::factory()->create([
            'carrier_id' => $fast->id,
            'origin_country' => 'SN',
            'destination_country' => 'CI',
            'mode' => 'road',
            'transit_days' => 1,
        ]);

        $response = $this->postJson('/api/v1/logistics/carriers/select', [
            'origin_country' => 'SN',
            'destination_country' => 'CI',
            'transport_mode' => 'road',
            'priority' => 'fastest',
        ]);

        $response->assertStatus(200);
        $options = $response->json('data');

        $this->assertSame($fast->id, $options[0]['carrier']['id']);
        $this->assertSame(1, $options[0]['transit_days']);
    }

    public function test_carrier_selection_validates_required_lane_fields(): void
    {
        $response = $this->postJson('/api/v1/logistics/carriers/select', [
            'transport_mode' => 'road',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['origin_country', 'destination_country']);
    }
}
