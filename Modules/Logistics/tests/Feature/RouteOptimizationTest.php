<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Logistics\Services\RouteOptimizationService;
use Spatie\Permission\Models\Role;

/**
 * Originally written against an invented `DeliveryRound`-based
 * RouteOptimizationService contract — `optimizeRoute($deliveryRoundModel)`,
 * `getOptimizationComparison()`, `clearCache()`, `getSuggestedAlternatives()` —
 * none of which exist. The real `RouteOptimizationService::optimizeRoute()`
 * takes an `int $routeId` against `DeliveryRoute`/`RouteStop`
 * (`lgx_delivery_routes`/`lgx_route_stops`), routed as
 * `PUT logistics/routes/{id}/optimize` via `CustomsRouteController`.
 *
 * Investigation found route optimization genuinely has TWO implementations,
 * but only one of them is actually reachable end-to-end today:
 *
 *  - `RouteOptimizationService` (nearest-neighbour on DeliveryRoute/RouteStop):
 *    its pure Haversine helper `calculateDistance()` has no DB dependency and
 *    is real, working code — exercised directly below. Its DB-backed methods
 *    (`optimizeRoute()`, `getRouteKpis()`, `suggestVehicle()`, ...) currently
 *    have NO backing migration for `lgx_delivery_routes`/`lgx_route_stops`
 *    (confirmed via `Schema::hasTable()` — both return false), so calling
 *    them 500s despite the controller/route existing. This is a genuine,
 *    pre-existing product gap, not something this test-only fix introduces
 *    or is scoped to repair (would require a new migration = new business
 *    logic), so those methods are intentionally NOT exercised here.
 *  - `RouteOptimizerService` (full VRP solver, 2-opt + time windows, routed
 *    as `POST logistics/routes/optimize`) IS fully functional — it operates
 *    on request arrays with no DB dependency — and already has exhaustive
 *    unit + HTTP coverage in `VrpRouteOptimizerTest`. One smoke test against
 *    the real endpoint is kept here so "route optimization" stays covered
 *    under this filename too, without duplicating that file's edge cases.
 */
describe('RouteOptimizationService::calculateDistance (Haversine)', function () {
    beforeEach(function () {
        $this->service = new RouteOptimizationService();
    });

    test('calculates a realistic long-haul distance', function () {
        // NYC -> LA, great-circle distance is ~3936 km
        $distance = $this->service->calculateDistance(40.7128, -74.0060, 34.0522, -118.2437);

        expect($distance)->toBeGreaterThan(3800.0)->toBeLessThan(4100.0);
    });

    test('distance between identical points is zero', function () {
        $distance = $this->service->calculateDistance(18.8792, 47.5079, 18.8792, 47.5079);

        expect($distance)->toBe(0.0);
    });

    test('distance is symmetric', function () {
        $ab = $this->service->calculateDistance(14.6937, -17.4441, 14.7910, -16.9260);
        $ba = $this->service->calculateDistance(14.7910, -16.9260, 14.6937, -17.4441);

        expect($ab)->toBe($ba);
    });
});

describe('Route Optimization — real routed VRP endpoint', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }
        Role::firstOrCreate(['name' => 'logistics-manager', 'guard_name' => 'web']);
        $this->user->assignRole('logistics-manager');
    });

    test('optimize delivery route with multiple stops via POST /routes/optimize', function () {
        $stops = [
            ['id' => 's1', 'lat' => 40.7128, 'lng' => -74.0060],
            ['id' => 's2', 'lat' => 40.7580, 'lng' => -73.9855],
            ['id' => 's3', 'lat' => 40.7489, 'lng' => -73.9680],
        ];
        $vehicles = [['id' => 'v1', 'capacity' => 500, 'start_lat' => 40.7128, 'start_lng' => -74.0060]];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/logistics/routes/optimize', compact('stops', 'vehicles'));

        $response->assertStatus(200)->assertJsonPath('status', 'completed');
        expect($response->json('result.routes.0.stops'))->toHaveCount(3);
        expect($response->json('result.total_distance_km'))->toBeGreaterThan(0.0);
    });
});
