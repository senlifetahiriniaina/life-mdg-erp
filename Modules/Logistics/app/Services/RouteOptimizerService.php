<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Log;

/**
 * Vehicle Routing Problem solver with time-window support.
 *
 * Algorithm: Nearest-neighbour construction → 2-opt local search (max 100 iterations)
 * → time-window feasibility check with soft penalty (always returns a solution).
 *
 * Pure PHP, no external dependencies.
 * Africa First: distances in km, costs in XOF.
 */
class RouteOptimizerService
{
    private const AVG_SPEED_KMH        = 40.0;
    private const MAX_2OPT_ITERATIONS  = 100;
    private const TIME_WINDOW_PENALTY  = 30; // minutes penalty per violation
    private const DEFAULT_MAX_STOPS    = 50;
    private const DEFAULT_SERVICE_TIME = 10; // minutes per stop

    /**
     * Solve VRP for multiple vehicles with time windows.
     *
     * @param  array<int, array{id: string, lat: float, lng: float, time_window_open: string|null, time_window_close: string|null, service_time_minutes: int, demand: float}> $stops
     * @param  array<int, array{id: string, capacity: float, start_lat: float, start_lng: float, max_stops: int}> $vehicles
     * @return array{routes: array, unserved: array, total_distance_km: float, solver_info: array}
     */
    public function solve(array $stops, array $vehicles): array
    {
        if (empty($stops) || empty($vehicles)) {
            return [
                'routes'            => [],
                'unserved'          => [],
                'total_distance_km' => 0.0,
                'solver_info'       => [
                    'algorithm'          => 'nearest-neighbour + 2-opt',
                    'stops_input'        => count($stops),
                    'vehicles_input'     => count($vehicles),
                    'stops_served'       => 0,
                    'stops_unserved'     => 0,
                ],
            ];
        }

        // Sort stops by time_window_open (nulls last) to prioritise time-sensitive deliveries
        usort($stops, function (array $a, array $b): int {
            $openA = $this->parseTimeToMinutes($a['time_window_open'] ?? null);
            $openB = $this->parseTimeToMinutes($b['time_window_open'] ?? null);

            if ($openA === null && $openB === null) {
                return 0;
            }
            if ($openA === null) {
                return 1;
            }
            if ($openB === null) {
                return -1;
            }

            return $openA <=> $openB;
        });

        $remaining = array_values($stops);
        $routes    = [];

        foreach ($vehicles as $vehicle) {
            if (empty($remaining)) {
                break;
            }

            $maxStops = (int) ($vehicle['max_stops'] ?? self::DEFAULT_MAX_STOPS);
            $capacity = (float) ($vehicle['capacity'] ?? PHP_FLOAT_MAX);

            // Greedily assign stops to this vehicle (capacity + max_stops)
            $assigned     = [];
            $loadSoFar    = 0.0;
            $newRemaining = [];

            foreach ($remaining as $stop) {
                $demand = (float) ($stop['demand'] ?? 0.0);

                if (
                    count($assigned) < $maxStops
                    && ($loadSoFar + $demand) <= $capacity
                ) {
                    $assigned[]  = $stop;
                    $loadSoFar  += $demand;
                } else {
                    $newRemaining[] = $stop;
                }
            }

            $remaining = $newRemaining;

            if (empty($assigned)) {
                continue;
            }

            // Nearest-neighbour construction starting from vehicle depot
            $startPoint = [
                'lat' => (float) $vehicle['start_lat'],
                'lng' => (float) $vehicle['start_lng'],
            ];
            $ordered = $this->nearestNeighbourConstruct($startPoint, $assigned);

            // 2-opt improvement
            $improved = $this->twoOptImprove($ordered);

            // Time-window feasibility check
            $twCheck = $this->checkTimeWindows($improved, $vehicle);

            $distKm   = $this->totalDistance($improved);
            $timeMin  = $this->totalTime($improved);

            $routes[] = [
                'vehicle_id'        => $vehicle['id'],
                'stops'             => $improved,
                'total_distance_km' => round($distKm, 3),
                'total_time_min'    => $timeMin,
                'feasible'          => $twCheck['feasible'],
                'tw_violations'     => $twCheck['violations'],
                'tw_penalty_min'    => $twCheck['total_penalty_min'],
                'arrival_times'     => $twCheck['arrival_times'],
                'total_demand'      => $loadSoFar,
            ];
        }

        $totalDistance = array_sum(array_column($routes, 'total_distance_km'));

        Log::info('RouteOptimizerService: VRP solved', [
            'stops_input'    => count($stops),
            'vehicles'       => count($vehicles),
            'routes_created' => count($routes),
            'unserved'       => count($remaining),
            'total_km'       => $totalDistance,
        ]);

        return [
            'routes'            => $routes,
            'unserved'          => array_values($remaining),
            'total_distance_km' => round($totalDistance, 3),
            'solver_info'       => [
                'algorithm'      => 'nearest-neighbour + 2-opt',
                'stops_input'    => count($stops),
                'vehicles_input' => count($vehicles),
                'stops_served'   => count($stops) - count($remaining),
                'stops_unserved' => count($remaining),
            ],
        ];
    }

    /**
     * Nearest-neighbour construction for a single vehicle.
     * Starts from $startPoint and greedily picks the nearest unvisited stop.
     *
     * @param  array{lat: float, lng: float} $startPoint
     * @param  array<int, array<string, mixed>> $stops
     * @return array<int, array<string, mixed>>
     */
    private function nearestNeighbourConstruct(array $startPoint, array $stops): array
    {
        if (empty($stops)) {
            return [];
        }

        $remaining = array_values($stops);
        $ordered   = [];
        $current   = $startPoint;

        while (! empty($remaining)) {
            $nearestIdx  = 0;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($remaining as $idx => $stop) {
                $dist = $this->haversine(
                    (float) $current['lat'],
                    (float) $current['lng'],
                    (float) $stop['lat'],
                    (float) $stop['lng']
                );
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx  = $idx;
                }
            }

            $ordered[] = $remaining[$nearestIdx];
            $current   = $remaining[$nearestIdx];
            array_splice($remaining, $nearestIdx, 1);
        }

        return $ordered;
    }

    /**
     * 2-opt local search improvement.
     * For each pair (i, k): tries reversing the segment stops[i+1..k].
     * Accepts the reversal if total distance strictly improves.
     * Repeats until no improvement or MAX_2OPT_ITERATIONS reached.
     *
     * @param  array<int, array<string, mixed>> $stops ordered stops with lat/lng
     * @return array<int, array<string, mixed>> improved ordered stops
     */
    private function twoOptImprove(array $stops): array
    {
        $n = count($stops);
        if ($n < 3) {
            return $stops;
        }

        $improved  = true;
        $iteration = 0;

        while ($improved && $iteration < self::MAX_2OPT_ITERATIONS) {
            $improved = false;
            $iteration++;

            for ($i = 0; $i < $n - 1; $i++) {
                for ($k = $i + 1; $k < $n; $k++) {
                    // Distance of current edges: stops[i]→stops[i+1] and stops[k]→stops[k+1]
                    // After 2-opt: stops[i]→stops[k] and stops[i+1]→stops[k+1]
                    $distBefore = $this->haversine(
                        (float) $stops[$i]['lat'],
                        (float) $stops[$i]['lng'],
                        (float) $stops[$i + 1]['lat'],
                        (float) $stops[$i + 1]['lng']
                    );

                    if ($k + 1 < $n) {
                        $distBefore += $this->haversine(
                            (float) $stops[$k]['lat'],
                            (float) $stops[$k]['lng'],
                            (float) $stops[$k + 1]['lat'],
                            (float) $stops[$k + 1]['lng']
                        );

                        $distAfter = $this->haversine(
                            (float) $stops[$i]['lat'],
                            (float) $stops[$i]['lng'],
                            (float) $stops[$k]['lat'],
                            (float) $stops[$k]['lng']
                        ) + $this->haversine(
                            (float) $stops[$i + 1]['lat'],
                            (float) $stops[$i + 1]['lng'],
                            (float) $stops[$k + 1]['lat'],
                            (float) $stops[$k + 1]['lng']
                        );
                    } else {
                        // k is the last stop — no k+1 edge to compare
                        $distAfter = $this->haversine(
                            (float) $stops[$i]['lat'],
                            (float) $stops[$i]['lng'],
                            (float) $stops[$k]['lat'],
                            (float) $stops[$k]['lng']
                        );
                    }

                    if ($distAfter < $distBefore - 1e-10) {
                        // Reverse segment stops[i+1 .. k]
                        $segment = array_reverse(array_slice($stops, $i + 1, $k - $i));
                        array_splice($stops, $i + 1, $k - $i, $segment);
                        $improved = true;
                    }
                }
            }
        }

        return $stops;
    }

    /**
     * Check time-window feasibility for a route.
     * Simulates travel starting at current time (defaults to 08:00 when no time windows defined).
     *
     * Returns:
     *   feasible         — true if no violations
     *   violations       — number of late arrivals
     *   total_penalty_min— total penalty in minutes (violations × TIME_WINDOW_PENALTY)
     *   arrival_times    — [stop_id => arrival_minutes_since_midnight]
     *
     * @param  array<int, array<string, mixed>> $stops
     * @param  array<string, mixed>             $vehicle
     * @return array{feasible: bool, violations: int, total_penalty_min: int, arrival_times: array<string, int>}
     */
    private function checkTimeWindows(array $stops, array $vehicle): array
    {
        if (empty($stops)) {
            return ['feasible' => true, 'violations' => 0, 'total_penalty_min' => 0, 'arrival_times' => []];
        }

        // Determine whether any stop has a time window at all
        $hasTimeWindows = false;
        foreach ($stops as $stop) {
            if (! empty($stop['time_window_open']) || ! empty($stop['time_window_close'])) {
                $hasTimeWindows = true;
                break;
            }
        }

        // Start time: 08:00 (480 min) by default
        $currentTime  = 8 * 60;
        $prevLat      = (float) ($vehicle['start_lat'] ?? $stops[0]['lat']);
        $prevLng      = (float) ($vehicle['start_lng'] ?? $stops[0]['lng']);
        $violations   = 0;
        $totalPenalty = 0;
        $arrivalTimes = [];

        if (! $hasTimeWindows) {
            // No time windows — always feasible; still record travel-simulated arrival times
            foreach ($stops as $stop) {
                $distKm      = $this->haversine($prevLat, $prevLng, (float) $stop['lat'], (float) $stop['lng']);
                $travelMin   = (int) ceil(($distKm / self::AVG_SPEED_KMH) * 60);
                $currentTime += $travelMin;
                $arrivalTimes[(string) $stop['id']] = $currentTime;
                $serviceTime  = (int) ($stop['service_time_minutes'] ?? self::DEFAULT_SERVICE_TIME);
                $currentTime += $serviceTime;
                $prevLat = (float) $stop['lat'];
                $prevLng = (float) $stop['lng'];
            }

            return [
                'feasible'         => true,
                'violations'       => 0,
                'total_penalty_min'=> 0,
                'arrival_times'    => $arrivalTimes,
            ];
        }

        foreach ($stops as $stop) {
            $distKm      = $this->haversine($prevLat, $prevLng, (float) $stop['lat'], (float) $stop['lng']);
            $travelMin   = (int) ceil(($distKm / self::AVG_SPEED_KMH) * 60);
            $arrival     = $currentTime + $travelMin;

            $windowOpen  = $this->parseTimeToMinutes($stop['time_window_open']  ?? null);
            $windowClose = $this->parseTimeToMinutes($stop['time_window_close'] ?? null);

            // Wait if arriving before window opens (no penalty)
            if ($windowOpen !== null && $arrival < $windowOpen) {
                $arrival = $windowOpen;
            }

            // Late arrival — soft penalty
            if ($windowClose !== null && $arrival > $windowClose) {
                $violations++;
                $totalPenalty += self::TIME_WINDOW_PENALTY;
            }

            $arrivalTimes[(string) $stop['id']] = $arrival;
            $serviceTime  = (int) ($stop['service_time_minutes'] ?? self::DEFAULT_SERVICE_TIME);
            $currentTime  = $arrival + $serviceTime;
            $prevLat      = (float) $stop['lat'];
            $prevLng      = (float) $stop['lng'];
        }

        return [
            'feasible'          => $violations === 0,
            'violations'        => $violations,
            'total_penalty_min' => $totalPenalty,
            'arrival_times'     => $arrivalTimes,
        ];
    }

    /**
     * Haversine distance in km between two geo-coordinates.
     */
    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Calculate total route distance in km (sum of consecutive haversine pairs).
     *
     * @param  array<int, array<string, mixed>> $stops
     */
    private function totalDistance(array $stops): float
    {
        $total = 0.0;
        $n     = count($stops);

        for ($i = 0; $i < $n - 1; $i++) {
            $total += $this->haversine(
                (float) $stops[$i]['lat'],
                (float) $stops[$i]['lng'],
                (float) $stops[$i + 1]['lat'],
                (float) $stops[$i + 1]['lng']
            );
        }

        return $total;
    }

    /**
     * Calculate total route time in minutes (travel + service times).
     *
     * @param  array<int, array<string, mixed>> $stops
     */
    private function totalTime(array $stops): int
    {
        $distKm      = $this->totalDistance($stops);
        $travelMin   = (int) ceil(($distKm / self::AVG_SPEED_KMH) * 60);
        $serviceMin  = 0;

        foreach ($stops as $stop) {
            $serviceMin += (int) ($stop['service_time_minutes'] ?? self::DEFAULT_SERVICE_TIME);
        }

        return $travelMin + $serviceMin;
    }

    /**
     * Parse time string "HH:MM" to minutes since midnight.
     * Returns null if the input is null or malformed.
     */
    private function parseTimeToMinutes(?string $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        $hours   = (int) $parts[0];
        $minutes = (int) $parts[1];

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return $hours * 60 + $minutes;
    }
}
