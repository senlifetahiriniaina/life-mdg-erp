<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\Carrier;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Models\ShipmentEvent;


it('can list shipments', function () {
    $user = User::factory()->create();
    Shipment::factory()->count(4)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/inventory/shipments')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonCount(4, 'data');
});

it('can create a shipment', function () {
    $user = User::factory()->create();
    $carrier = Carrier::factory()->dhl()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/inventory/shipments', [
            'carrier_id' => $carrier->id,
            'origin_address' => [
                'name' => 'WideHalo SAS',
                'street' => '12 Rue de la Paix',
                'city' => 'Paris',
                'zip' => '75001',
                'country' => 'FR',
            ],
            'destination_address' => [
                'name' => 'Jean Dupont',
                'street' => '5 Avenue Foch',
                'city' => 'Lyon',
                'zip' => '69001',
                'country' => 'FR',
            ],
            'weight_kg' => 2.5,
            'service_type' => 'standard',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'booked')
        ->assertJsonStructure(['data' => ['id', 'reference', 'tracking_number', 'status']]);

    expect(Shipment::count())->toBe(1);
    expect(ShipmentEvent::count())->toBeGreaterThan(0);
});

it('can get shipping rates', function () {
    $user = User::factory()->create();
    Carrier::factory()->dhl()->create();
    Carrier::factory()->fedex()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/inventory/shipments/rates', [
            'origin_address' => [
                'name' => 'WideHalo SAS',
                'street' => '12 Rue de la Paix',
                'city' => 'Paris',
                'country' => 'FR',
            ],
            'destination_address' => [
                'name' => 'Client',
                'street' => '5 Avenue Foch',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'weight_kg' => 1.0,
        ])
        ->assertOk()
        ->assertJsonStructure(['data']);

    $rates = $response->json('data');
    expect($rates)->not->toBeEmpty();
    expect($rates[0])->toHaveKeys(['carrier_id', 'carrier_name', 'service', 'price', 'days']);
});

it('can track a shipment', function () {
    $user = User::factory()->create();
    $shipment = Shipment::factory()->inTransit()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/inventory/shipments/{$shipment->id}/track")
        ->assertOk()
        ->assertJsonStructure(['shipment', 'events']);

    expect($response->json('events'))->not->toBeEmpty();
});

it('can update shipment status', function () {
    $user = User::factory()->create();
    $shipment = Shipment::factory()->draft()->create();

    $this->actingAs($user, 'sanctum')
        ->patchJson("/api/v1/inventory/shipments/{$shipment->id}", [
            'status' => 'in_transit',
            'location' => 'Paris CDG',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_transit');

    expect(ShipmentEvent::where('shipment_id', $shipment->id)->where('status', 'in_transit')->exists())->toBeTrue();
});

it('can manage carriers', function () {
    $user = User::factory()->create();

    // Create a carrier
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/inventory/carriers', [
            'name' => 'My Carrier',
            'code' => 'mycarrier',
            'tracking_url_template' => 'https://track.mycarrier.com/{tracking_number}',
            'active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.code', 'mycarrier');

    // List carriers
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/inventory/carriers')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can filter shipments by status', function () {
    $user = User::factory()->create();
    Shipment::factory()->inTransit()->count(3)->create();
    Shipment::factory()->delivered()->count(2)->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/inventory/shipments?status=in_transit')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('can soft delete a shipment', function () {
    $user = User::factory()->create();
    $shipment = Shipment::factory()->draft()->create();

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/inventory/shipments/{$shipment->id}")
        ->assertNoContent();

    expect(Shipment::withTrashed()->find($shipment->id)?->deleted_at)->not->toBeNull();
    expect(Shipment::find($shipment->id))->toBeNull();
});
