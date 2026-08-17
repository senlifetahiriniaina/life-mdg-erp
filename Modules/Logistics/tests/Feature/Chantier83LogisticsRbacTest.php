<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\DeliveryRound;
use Modules\Logistics\Models\Shipment;

/**
 * Chantier 8.3 (Logistics): CarrierPolicy/ShipmentPolicy/DeliveryRoundPolicy
 * were fully written but never invoked by their controllers, and never
 * registered with Laravel's Gate. Locks in the fix — a plain-role user
 * (no logistics permissions) must be denied; a security-admin-equivalent
 * (logistics-manager) must still work.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('plain employee cannot create a carrier', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('sales-rep');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/carriers', [
        'name' => 'Unauthorized Carrier',
    ]);

    $response->assertForbidden();
});

test('logistics manager can create a carrier', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('logistics-manager');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/carriers', [
        'name' => 'Authorized Carrier',
        'code' => 'AUC',
    ]);

    $response->assertCreated();
});

test('plain employee cannot delete a shipment', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('sales-rep');
    $shipment = Shipment::factory()->create();

    $response = test()->actingAs($user, 'sanctum')->deleteJson("/api/v1/logistics/shipments/{$shipment->id}");

    $response->assertForbidden();
});

test('plain employee cannot create a delivery round', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('sales-rep');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/delivery-rounds', [
        'driver_name' => 'John Doe',
    ]);

    $response->assertForbidden();
});

test('logistics manager can create a delivery round', function () {
    test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('logistics-manager');

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/logistics/delivery-rounds', [
        'driver_name' => 'John Doe',
    ]);

    $response->assertCreated();
});
