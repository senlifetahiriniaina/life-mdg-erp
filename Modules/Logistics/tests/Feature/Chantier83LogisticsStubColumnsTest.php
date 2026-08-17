<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\Shipment;

/**
 * Chantier 8.3: several logistics_* tables were left as bare scaffold
 * stubs (id/tenant_id/status/data/timestamps) despite their models/
 * controllers already declaring real fields — every write below crashed
 * with a QueryException before the 2026_08_27_000001 patch migration.
 * Locks in the fix on the routed, active write paths.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function logisticsStubColumnsTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('logistics-manager');

    return $user;
}

test('creating a carrier with website/tracking_url_template/api_provider does not crash', function () {
    $user = logisticsStubColumnsTestUser();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/carriers', [
        'name' => 'Global Freight Co',
        'code' => 'GFC',
        'website' => 'https://globalfreight.example.com',
        'tracking_url_template' => 'https://track.example.com/{tracking_number}',
        'api_provider' => 'aftership',
        'notes' => 'Preferred carrier for EU lanes',
    ]);

    $response->assertCreated();
    expect($response->json('data.website'))->toBe('https://globalfreight.example.com');
});

test('creating a carrier rate with zone/validity fields does not crash', function () {
    $user = logisticsStubColumnsTestUser();
    $carrier = Carrier::factory()->create();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/carrier-rates', [
        'carrier_id' => $carrier->id,
        'name' => 'EU Standard Rate',
        'mode' => 'road',
        'origin_country' => 'FR',
        'destination_country' => 'DE',
        'origin_zone' => 'West EU',
        'destination_zone' => 'Central EU',
        'rate_type' => 'flat',
        'base_rate' => 45.50,
        'currency' => 'EUR',
        'valid_from' => now()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $response->assertCreated();
    expect($response->json('origin_zone'))->toBe('West EU');
});

test('creating a route with the full geographic field set does not crash', function () {
    $user = logisticsStubColumnsTestUser();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/routes', [
        'name' => 'Paris-Berlin Express',
        'code' => 'RT-EU-01',
        'origin_name' => 'Paris Hub',
        'origin_country' => 'FR',
        'destination_name' => 'Berlin Hub',
        'destination_country' => 'DE',
        'mode' => 'road',
        'distance_km' => 1050,
        'estimated_transit_days' => 2,
        'notes' => 'Primary EU corridor',
    ]);

    $response->assertCreated();
    expect($response->json('code'))->toBe('RT-EU-01');
});

test('booking and dispatching a shipment sets booked_at/picked_up_at without crashing', function () {
    $user = logisticsStubColumnsTestUser();
    $carrier = Carrier::factory()->create();
    $shipment = Shipment::factory()->create(['carrier_id' => $carrier->id, 'status' => 'draft']);

    test()->actingAs($user, 'sanctum')->postJson("/api/v1/logistics/shipments/{$shipment->id}/book")
        ->assertOk();

    $response = test()->actingAs($user, 'sanctum')->postJson("/api/v1/logistics/shipments/{$shipment->id}/dispatch");

    $response->assertOk();
    expect($shipment->fresh()->booked_at)->not->toBeNull();
    expect($shipment->fresh()->picked_up_at)->not->toBeNull();
});

test('creating a freight invoice does not crash', function () {
    $user = logisticsStubColumnsTestUser();
    $carrier = Carrier::factory()->create();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/freight-invoices', [
        'carrier_id' => $carrier->id,
        'type' => 'payable',
        'invoice_date' => now()->toDateString(),
        'currency' => 'EUR',
        'invoiced_amount' => 1200.50,
    ]);

    $response->assertCreated();
});

test('creating a putaway rule does not crash', function () {
    $user = logisticsStubColumnsTestUser();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/putaway-rules', [
        'name' => 'Cold chain priority',
        'location_id' => 1,
        'product_category' => 'perishables',
        'transport_mode' => 'road',
        'requires_cold_chain' => true,
        'priority' => 1,
    ]);

    $response->assertCreated();
    expect($response->json('product_category'))->toBe('perishables');
});
