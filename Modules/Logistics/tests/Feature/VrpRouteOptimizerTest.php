<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Logistics\Services\RouteOptimizerService;

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a minimal stop array with sequential IDs and given coordinates.
 *
 * @param  array<int, array{lat: float, lng: float}> $coords
 * @return array<int, array<string, mixed>>
 */
function makeStops(array $coords): array
{
    $stops = [];
    foreach ($coords as $i => $c) {
        $stops[] = [
            'id'                   => 's' . ($i + 1),
            'lat'                  => $c['lat'],
            'lng'                  => $c['lng'],
            'time_window_open'     => null,
            'time_window_close'    => null,
            'service_time_minutes' => 10,
            'demand'               => 10.0,
        ];
    }
    return $stops;
}

/**
 * Build a minimal vehicle starting at the given depot.
 */
function makeVehicle(string $id, float $lat, float $lng, float $capacity = 1000.0, int $maxStops = 50): array
{
    return [
        'id'        => $id,
        'capacity'  => $capacity,
        'start_lat' => $lat,
        'start_lng' => $lng,
        'max_stops' => $maxStops,
    ];
}

// ── Service unit tests ───────────────────────────────────────────────────────

describe('RouteOptimizerService — unit', function () {

    beforeEach(function () {
        $this->optimizer = new RouteOptimizerService();
    });

    test('empty stops returns empty routes and unserved', function () {
        $result = $this->optimizer->solve([], [makeVehicle('v1', 14.7167, -17.4677)]);

        expect($result['routes'])->toBeEmpty()
            ->and($result['unserved'])->toBeEmpty()
            ->and($result['total_distance_km'])->toBe(0.0);
    });

    test('small instance (3 stops, 1 vehicle) returns feasible route', function () {
        $stops = makeStops([
            ['lat' => 14.7167, 'lng' => -17.4677],
            ['lat' => 14.6937, 'lng' => -17.4441],
            ['lat' => 14.7340, 'lng' => -17.4500],
        ]);

        $vehicles = [makeVehicle('v1', 14.7167, -17.4677)];
        $result   = $this->optimizer->solve($stops, $vehicles);

        expect($result['routes'])->toHaveCount(1)
            ->and($result['unserved'])->toBeEmpty()
            ->and($result['routes'][0]['stops'])->toHaveCount(3)
            ->and($result['routes'][0]['total_distance_km'])->toBeGreaterThan(0.0)
            ->and($result['routes'][0]['feasible'])->toBeTrue();
    });

    test('2-opt improves or maintains distance vs naive reverse order', function () {
        // Arrange stops in a deliberately bad order (far-far-near pattern)
        // v: depot=(0,0); stops: A=(1,0), B=(0,1), C=(-1,0) — nearest-neighbour alone handles this,
        // but we test that 2-opt does not make it worse.
        $stops = [
            ['id' => 'a', 'lat' => 0.01,  'lng' => 0.00,  'time_window_open' => null, 'time_window_close' => null, 'service_time_minutes' => 5, 'demand' => 1.0],
            ['id' => 'b', 'lat' => 0.00,  'lng' => 0.01,  'time_window_open' => null, 'time_window_close' => null, 'service_time_minutes' => 5, 'demand' => 1.0],
            ['id' => 'c', 'lat' => -0.01, 'lng' => 0.00,  'time_window_open' => null, 'time_window_close' => null, 'service_time_minutes' => 5, 'demand' => 1.0],
            ['id' => 'd', 'lat' => 0.00,  'lng' => -0.01, 'time_window_open' => null, 'time_window_close' => null, 'service_time_minutes' => 5, 'demand' => 1.0],
        ];

        $vehicle = makeVehicle('v1', 0.0, 0.0);
        $result  = $this->optimizer->solve($stops, [$vehicle]);

        expect($result['routes'])->not->toBeEmpty()
            ->and($result['routes'][0]['total_distance_km'])->toBeGreaterThan(0.0);

        // Verify 2-opt: distance should be ≤ the un-optimised (reversed) order distance
        $reversed = array_reverse($stops);
        $optimizer = new RouteOptimizerService();
        $haversine = fn($a, $b) => $optimizer->haversine($a['lat'], $a['lng'], $b['lat'], $b['lng']);

        $reversedDist = 0.0;
        for ($i = 0; $i < count($reversed) - 1; $i++) {
            $reversedDist += $haversine($reversed[$i], $reversed[$i + 1]);
        }

        expect($result['routes'][0]['total_distance_km'])->toBeLessThanOrEqual(round($reversedDist, 3) + 0.001);
    });

    test('capacity constraint sends excess stops to unserved', function () {
        // 3 stops each with demand=50; vehicle capacity=80 → fits 1 stop, 2 go to unserved
        $stops = [
            ['id' => 's1', 'lat' => 14.72, 'lng' => -17.45, 'time_window_open' => null, 'time_window_close' => null, 'service_time_minutes' => 5, 'demand' => 50.0],
            ['id' => 's2', 'lat' => 14.70, 'lng' => -17.44, 'time_window_open' => null, 'time_window_close' => null, 'service_time_minutes' => 5, 'demand' => 50.0],
            ['id' => 's3', 'lat' => 14.68, 'lng' => -17.43, 'time_window_open' => null, 'time_window_close' => null, 'service_time_minutes' => 5, 'demand' => 50.0],
        ];

        $vehicle = makeVehicle('v1', 14.72, -17.46, 80.0); // capacity=80 kg
        $result  = $this->optimizer->solve($stops, [$vehicle]);

        expect($result['routes'][0]['stops'])->toHaveCount(1)
            ->and($result['unserved'])->toHaveCount(2);
    });

    test('max_stops constraint sends excess stops to unserved', function () {
        // 5 stops but vehicle max_stops=2
        $stops   = makeStops([
            ['lat' => 14.72, 'lng' => -17.45],
            ['lat' => 14.70, 'lng' => -17.44],
            ['lat' => 14.68, 'lng' => -17.43],
            ['lat' => 14.66, 'lng' => -17.42],
            ['lat' => 14.64, 'lng' => -17.41],
        ]);
        $vehicle = makeVehicle('v1', 14.74, -17.47, 9999.0, 2);
        $result  = $this->optimizer->solve($stops, [$vehicle]);

        expect($result['routes'][0]['stops'])->toHaveCount(2)
            ->and($result['unserved'])->toHaveCount(3);
    });

    test('multi-vehicle distributes stops across vehicles', function () {
        // 4 stops, 2 vehicles each with max_stops=2
        $stops    = makeStops([
            ['lat' => 14.72, 'lng' => -17.45],
            ['lat' => 14.70, 'lng' => -17.44],
            ['lat' => 14.68, 'lng' => -17.43],
            ['lat' => 14.66, 'lng' => -17.42],
        ]);
        $vehicles = [
            makeVehicle('v1', 14.74, -17.47, 9999.0, 2),
            makeVehicle('v2', 14.74, -17.47, 9999.0, 2),
        ];

        $result = $this->optimizer->solve($stops, $vehicles);

        $totalAssigned = array_sum(array_map(fn($r) => count($r['stops']), $result['routes']));

        expect($result['routes'])->toHaveCount(2)
            ->and($result['unserved'])->toBeEmpty()
            ->and($totalAssigned)->toBe(4);
    });

    test('time window feasibility: stop in-window is marked feasible', function () {
        // Single stop with window 09:00–17:00 — should always be feasible
        $stops = [[
            'id'                   => 's1',
            'lat'                  => 14.7167,
            'lng'                  => -17.4677,
            'time_window_open'     => '09:00',
            'time_window_close'    => '17:00',
            'service_time_minutes' => 10,
            'demand'               => 10.0,
        ]];
        $vehicle = makeVehicle('v1', 14.7100, -17.4600);
        $result  = $this->optimizer->solve($stops, [$vehicle]);

        expect($result['routes'][0]['feasible'])->toBeTrue()
            ->and($result['routes'][0]['tw_violations'])->toBe(0);
    });

    test('time window violation: stop with impossible window is flagged', function () {
        // Window closes at 00:01 — effectively impossible to reach in time
        $stops = [[
            'id'                   => 's1',
            'lat'                  => 14.7167,
            'lng'                  => -17.4677,
            'time_window_open'     => '00:00',
            'time_window_close'    => '00:01',
            'service_time_minutes' => 5,
            'demand'               => 5.0,
        ]];
        // Depot far from the stop (slow travel means arrival after 00:01)
        $vehicle = makeVehicle('v1', 5.0, 5.0); // far depot
        $result  = $this->optimizer->solve($stops, [$vehicle]);

        expect($result['routes'][0]['feasible'])->toBeFalse()
            ->and($result['routes'][0]['tw_violations'])->toBeGreaterThanOrEqual(1);
    });

    test('haversine returns reasonable distance for known pair', function () {
        $optimizer = new RouteOptimizerService();

        // Dakar to Thiès: ~70 km
        $dist = $optimizer->haversine(14.6937, -17.4441, 14.7910, -16.9260);

        expect($dist)->toBeGreaterThan(50.0)->toBeLessThan(100.0);
    });
});

// ── HTTP endpoint tests ───────────────────────────────────────────────────────

describe('POST /api/v1/logistics/routes/optimize', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    test('synchronous response for <= 20 stops', function () {
        $stops = array_map(fn($i) => [
            'id'                   => "s{$i}",
            'lat'                  => 14.7 + $i * 0.01,
            'lng'                  => -17.4 - $i * 0.01,
            'time_window_open'     => null,
            'time_window_close'    => null,
            'service_time_minutes' => 10,
            'demand'               => 10,
        ], range(1, 5));

        $vehicles = [['id' => 'v1', 'capacity' => 1000, 'start_lat' => 14.7167, 'start_lng' => -17.4677, 'max_stops' => 50]];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/logistics/routes/optimize', compact('stops', 'vehicles'))
            ->assertStatus(200)
            ->assertJsonPath('status', 'completed')
            ->assertJsonStructure(['status', 'result' => ['routes', 'unserved', 'total_distance_km', 'solver_info']]);
    });

    test('queued (job_id) response for > 20 stops', function () {
        $stops = array_map(fn($i) => [
            'id'                   => "s{$i}",
            'lat'                  => 14.5 + ($i * 0.003),
            'lng'                  => -17.5 + ($i * 0.003),
            'time_window_open'     => null,
            'time_window_close'    => null,
            'service_time_minutes' => 5,
            'demand'               => 5,
        ], range(1, 25));

        $vehicles = [['id' => 'v1', 'capacity' => 9999, 'start_lat' => 14.7167, 'start_lng' => -17.4677, 'max_stops' => 50]];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/logistics/routes/optimize', compact('stops', 'vehicles'))
            ->assertStatus(202)
            ->assertJsonPath('status', 'queued')
            ->assertJsonStructure(['status', 'job_id']);
    });

    test('validation rejects missing stops', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/logistics/routes/optimize', [
                'vehicles' => [['id' => 'v1', 'capacity' => 500, 'start_lat' => 14.7, 'start_lng' => -17.4]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stops']);
    });

    test('validation rejects missing vehicles', function () {
        $stops = [['id' => 's1', 'lat' => 14.7, 'lng' => -17.4]];

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/logistics/routes/optimize', compact('stops'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vehicles']);
    });

    test('unauthenticated request is rejected', function () {
        $stops    = [['id' => 's1', 'lat' => 14.7, 'lng' => -17.4, 'demand' => 10]];
        $vehicles = [['id' => 'v1', 'capacity' => 500, 'start_lat' => 14.7, 'start_lng' => -17.4]];

        $this->postJson('/api/v1/logistics/routes/optimize', compact('stops', 'vehicles'))
            ->assertStatus(401);
    });
});

describe('GET /api/v1/logistics/routes/optimize/{jobId}/result', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    test('returns result for valid completed job', function () {
        $jobId = 'vrp_test_' . uniqid();
        Cache::put("vrp_job_{$jobId}", ['status' => 'completed', 'result' => ['routes' => [], 'unserved' => [], 'total_distance_km' => 0.0, 'solver_info' => []]], 3600);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/logistics/routes/optimize/{$jobId}/result")
            ->assertStatus(200)
            ->assertJsonPath('status', 'completed');
    });

    test('returns 404 for unknown job ID', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/logistics/routes/optimize/vrp_nonexistent_999/result')
            ->assertStatus(404)
            ->assertJsonPath('error', 'Job not found or expired');
    });
});
