<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\Location;
use Modules\Logistics\Models\Shipment;
use Spatie\Permission\Models\Role;


beforeEach(function () {
    $this->user = User::factory()->create();
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
    $this->user->assignRole('logistics-manager');
    $this->carrier = Carrier::factory()->create();
    $this->warehouse = Location::factory()->create(['type' => 'warehouse', 'name' => 'Main Warehouse']);
    $this->customer1 = Location::factory()->create(['type' => 'customer', 'name' => 'Customer 1']);
    $this->customer2 = Location::factory()->create(['type' => 'customer', 'name' => 'Customer 2']);
});

test('complete shipment workflow', function () {
    $shipmentResponse = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/shipments', [
            'carrier_id' => $this->carrier->id,
            'origin_location_id' => $this->warehouse->id,
            'destination_location_id' => $this->customer1->id,
            'reference' => 'SHIP-WF-001',
            'tracking_number' => 'TRACK-001',
            'status' => 'pending',
            'weight_kg' => 15.0,
        ]);

    expect($shipmentResponse->status())->toBe(201);
    $shipmentId = $shipmentResponse->json('data.id');

    $transitResponse = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/logistics/shipments/{$shipmentId}", ['status' => 'in_transit']);

    expect($transitResponse->status())->toBe(200);
    expect($transitResponse->json('data.status'))->toBe('in_transit');

    $deliverResponse = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/logistics/shipments/{$shipmentId}/deliver", [
            'delivery_date' => now()->toDateString(),
            'notes' => 'Left with reception',
        ]);

    expect($deliverResponse->status())->toBe(200);
    expect($deliverResponse->json('data.status'))->toBe('delivered');
});

test('delivery round workflow', function () {
    $roundResponse = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/delivery-rounds', [
            'carrier_id' => $this->carrier->id,
            'date' => now()->toDateString(),
            'driver_name' => 'John Doe',
            'vehicle_code' => 'VEH-001',
            'route' => 'North District',
            'status' => 'scheduled',
        ]);

    expect($roundResponse->status())->toBe(201);
    $roundId = $roundResponse->json('data.id');

    $stop1Response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/logistics/delivery-rounds/{$roundId}/stops", [
            'location_id' => $this->customer1->id,
            'sequence' => 1,
            'delivery_window' => '09:00-11:00',
        ]);

    expect($stop1Response->status())->toBe(201);

    $stop2Response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/logistics/delivery-rounds/{$roundId}/stops", [
            'location_id' => $this->customer2->id,
            'sequence' => 2,
            'delivery_window' => '11:30-13:00',
        ]);

    expect($stop2Response->status())->toBe(201);
});

test('multi shipment optimization', function () {
    $shipment1 = Shipment::factory()->create([
        'carrier_id' => $this->carrier->id,
        'origin_location_id' => $this->warehouse->id,
        'destination_location_id' => $this->customer1->id,
        'status' => 'pending',
    ]);

    $shipment2 = Shipment::factory()->create([
        'carrier_id' => $this->carrier->id,
        'origin_location_id' => $this->warehouse->id,
        'destination_location_id' => $this->customer2->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/routes/optimize', [
            'stops' => [
                ['id' => (string) $shipment1->id, 'lat' => 18.8792, 'lng' => 47.5079],
                ['id' => (string) $shipment2->id, 'lat' => 18.9146, 'lng' => 47.5316],
            ],
            'vehicles' => [
                ['id' => 'v1', 'capacity' => 500, 'start_lat' => 18.8792, 'start_lng' => 47.5079],
            ],
        ]);

    expect($response->status())->toBe(200);
});

test('shipment tracking chain', function () {
    $shipment = Shipment::factory()->create([
        'carrier_id' => $this->carrier->id,
        'status' => 'pending',
    ]);

    $updates = [
        'pending' => 'Created',
        'picked_up' => 'Picked up from warehouse',
        'in_transit' => 'In transit',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
    ];

    foreach ($updates as $status => $note) {
        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/logistics/shipments/{$shipment->id}", [
                'status' => $status,
                'tracking_notes' => $note,
            ]);
    }

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/logistics/shipments/{$shipment->id}/tracking");

    expect($response->status())->toBe(200);
});

test('concurrent shipment updates', function () {
    $shipment = Shipment::factory()->create(['status' => 'pending']);

    $response1 = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/logistics/shipments/{$shipment->id}", ['status' => 'in_transit']);

    $response2 = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/logistics/shipments/{$shipment->id}", ['status' => 'delivered']);

    expect($response2->status())->toBe(200);
    expect($response2->json('data.status'))->toBe('delivered');
});

test('shipment with customs declaration', function () {
    $shipment = Shipment::factory()->create([
        'carrier_id' => $this->carrier->id,
        'origin_location_id' => $this->warehouse->id,
        'destination_location_id' => $this->customer1->id,
    ]);

    $customsResponse = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/customs-declarations', [
            'shipment_id' => $shipment->id,
            'hs_code' => '6204.62.20',
            'item_description' => 'Cotton T-shirts',
            'quantity' => 100,
            'declared_value' => 500,
            'currency' => 'USD',
            'country_of_origin' => 'IN',
            'declaration_number' => 'CUST-INTL-001',
        ]);

    expect($customsResponse->status())->toBe(201);

    $shipmentResponse = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/logistics/shipments/{$shipment->id}");

    expect($shipmentResponse->status())->toBe(200);
});
