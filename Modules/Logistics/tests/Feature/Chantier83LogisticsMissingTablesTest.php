<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CarrierRateCard;
use Modules\Logistics\Models\DeliveryRoute;
use Modules\Logistics\Models\RouteStop;

/**
 * Chantier 8.3: DeliveryRoute/RouteStop/CarrierRateCard are real models
 * already used by live, routed code (RouteOptimizationService behind
 * CustomsRouteController's optimize/start/complete/stop-complete
 * endpoints, CarrierIntegrationService::getRate() behind the carrier
 * rate-lookup endpoint) but their tables (lgx_delivery_routes,
 * lgx_route_stops, lgx_carrier_rate_cards) were never migrated — every
 * one of those endpoints 500'd with "table not found". Locks in the fix
 * added by 2026_08_27_000002_create_lgx_delivery_routes_and_carrier_rate_cards.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function logisticsMissingTablesTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('logistics-manager');

    return $user;
}

test('starting a delivery route does not crash', function () {
    $user = logisticsMissingTablesTestUser();
    $route = DeliveryRoute::factory()->create(['company_id' => $user->company_id ?? 1, 'status' => 'planned']);

    $response = test()->actingAs($user, 'sanctum')->putJson("/api/v1/logistics/routes/{$route->id}/start");

    $response->assertOk();
    expect($route->fresh()->status)->toBe('in_progress');
});

test('optimizing a delivery route with stops does not crash', function () {
    $user = logisticsMissingTablesTestUser();
    $route = DeliveryRoute::factory()->create(['company_id' => $user->company_id ?? 1, 'status' => 'planned']);
    RouteStop::factory()->count(3)->create(['route_id' => $route->id]);

    $response = test()->actingAs($user, 'sanctum')->putJson("/api/v1/logistics/routes/{$route->id}/optimize");

    $response->assertOk();
});

test('completing a route stop does not crash', function () {
    $user = logisticsMissingTablesTestUser();
    $route = DeliveryRoute::factory()->create(['company_id' => $user->company_id ?? 1, 'status' => 'in_progress']);
    $stop = RouteStop::factory()->create(['route_id' => $route->id, 'status' => 'pending']);

    $response = test()->actingAs($user, 'sanctum')->putJson(
        "/api/v1/logistics/routes/{$route->id}/stops/{$stop->id}/complete",
        ['proof_of_delivery' => 'Signed by consignee']
    );

    $response->assertOk();
    expect($stop->fresh()->status)->toBe('completed');
});

test('looking up a carrier rate card does not crash', function () {
    $user = logisticsMissingTablesTestUser();
    $carrier = Carrier::factory()->create();
    CarrierRateCard::factory()->create([
        'carrier_id' => $carrier->id,
        'origin_country' => 'SN',
        'dest_country' => 'CI',
        'service_type' => 'standard',
        'weight_min_kg' => 0,
        'weight_max_kg' => 50,
        'is_active' => true,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson(
        "/api/v1/logistics/carriers/{$carrier->id}/rate?origin_country=SN&dest_country=CI&weight_kg=10"
    );

    $response->assertOk();
});
