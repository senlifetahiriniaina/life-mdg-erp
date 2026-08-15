<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\Location;
use Modules\Logistics\Models\Shipment;
use Spatie\Permission\Models\Role;


beforeEach(function () {
    $this->user = User::factory()->create();
    Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
    $this->user->assignRole('logistics-manager');
    $this->carrier = Carrier::factory()->create();
    $this->origin = Location::factory()->create(['type' => 'warehouse']);
    $this->destination = Location::factory()->create(['type' => 'customer']);
});

test('can create shipment', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/shipments', [
            'carrier_id' => $this->carrier->id,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'reference' => 'SHIP-001',
            'tracking_number' => 'TRK123456',
            'status' => 'pending',
            'weight_kg' => 25.5,
        ]);

    expect($response->status())->toBe(201);
    expect($response->json('data.reference'))->toBe('SHIP-001');
    $this->assertDatabaseHas('logistics_shipments', ['reference' => 'SHIP-001']);
});

test('can list shipments', function () {
    Shipment::factory(5)->create(['carrier_id' => $this->carrier->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/logistics/shipments');

    expect($response->status())->toBe(200);
    expect($response->json('meta.total'))->toBe(5);
    expect(count($response->json('data')))->toBe(5);
});

test('can get single shipment', function () {
    $shipment = Shipment::factory()->create(['carrier_id' => $this->carrier->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/logistics/shipments/{$shipment->id}");

    expect($response->status())->toBe(200);
    expect($response->json('data.id'))->toBe($shipment->id);
    expect($response->json('data.reference'))->toBe($shipment->reference);
});

test('can update shipment status', function () {
    $shipment = Shipment::factory()->create([
        'carrier_id' => $this->carrier->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/logistics/shipments/{$shipment->id}", [
            'status' => 'in_transit',
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('data.status'))->toBe('in_transit');
});

test('can mark shipment delivered', function () {
    $shipment = Shipment::factory()->create([
        'carrier_id' => $this->carrier->id,
        'status' => 'in_transit',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/logistics/shipments/{$shipment->id}/deliver", [
            'delivery_date' => now()->toDateString(),
            'notes' => 'Delivered successfully',
        ]);

    expect($response->status())->toBe(200);
    expect($response->json('data.status'))->toBe('delivered');
});

test('can delete shipment', function () {
    $shipment = Shipment::factory()->create([
        'carrier_id' => $this->carrier->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/v1/logistics/shipments/{$shipment->id}");

    expect($response->status())->toBe(200);
});

test('cannot access shipment without auth', function () {
    $shipment = Shipment::factory()->create();

    $response = $this->getJson("/api/v1/logistics/shipments/{$shipment->id}");

    expect($response->status())->toBe(401);
});

test('validates required fields', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/shipments', []);

    expect($response->status())->toBe(422);
    expect($response->json('errors'))->toHaveKeys(['carrier_id']);
});

test('validates unique reference', function () {
    Shipment::factory()->create(['reference' => 'SHIP-001']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/logistics/shipments', [
            'carrier_id' => $this->carrier->id,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'reference' => 'SHIP-001',
            'status' => 'pending',
        ]);

    expect($response->status())->toBe(422);
});

test('can filter by status', function () {
    Shipment::factory(3)->create(['status' => 'pending']);
    Shipment::factory(2)->create(['status' => 'in_transit']);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/logistics/shipments?status=pending');

    expect($response->status())->toBe(200);
    expect($response->json('meta.total'))->toBe(3);
});
