<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Logistics\Models\DeliveryRoute;
use Modules\Logistics\Models\RouteStop;
use Modules\Logistics\Models\Vehicle;

/**
 * Route optimization service.
 *
 * Nearest-neighbour heuristic for stop ordering (pure PHP, no external API).
 * Africa First: cost estimates in XOF; Haversine distance for African geographies.
 */
class RouteOptimizationService
{
    private const AVG_SPEED_KMH    = 40.0;
    private const DEFAULT_STOP_MIN = 15;

    private function generateReference(int $companyId): string
    {
        $year  = Carbon::now()->year;
        $count = DeliveryRoute::where('company_id', $companyId)
            ->whereYear('created_at', $year)->count() + 1;

        return sprintf('RTE-%d-%04d', $year, $count);
    }

    public function createRoute(array $data): DeliveryRoute
    {
        $stops = $data['stops'] ?? [];
        unset($data['stops']);

        $data['reference']   = $this->generateReference((int) $data['company_id']);
        $data['status']      = 'planned';
        $data['total_stops'] = count($stops);

        $route = DeliveryRoute::create($data);

        foreach ($stops as $index => $stop) {
            $stop['route_id'] = $route->id;
            $stop['sequence'] = $stop['sequence'] ?? ($index + 1);
            $stop['status']   = 'pending';
            RouteStop::create($stop);
        }

        return $route->load('stops');
    }

    /**
     * Optimize stop ordering using nearest-neighbour heuristic.
     * Updates sequences, estimates total distance and duration.
     */
    public function optimizeRoute(int $routeId): DeliveryRoute
    {
        $route = DeliveryRoute::with('stops')->findOrFail($routeId);
        $stops = $route->stops->toArray();

        if (count($stops) <= 1) {
            return $route;
        }

        $optimized = $this->nearestNeighbourOrder($stops);

        foreach ($optimized as $seq => $stop) {
            RouteStop::where('id', $stop['id'])->update(['sequence' => $seq + 1]);
        }

        $totalDistance = $this->calculateTotalDistance($optimized);
        $totalDuration = (int) (($totalDistance / self::AVG_SPEED_KMH) * 60)
            + (count($optimized) * self::DEFAULT_STOP_MIN);

        $route->update([
            'total_distance_km'  => round($totalDistance, 2),
            'total_duration_min' => $totalDuration,
            'optimized'          => true,
            'total_stops'        => count($optimized),
        ]);

        return $route->fresh(['stops']);
    }

    public function assignVehicle(int $routeId, int $vehicleId, int $driverId): DeliveryRoute
    {
        $route = DeliveryRoute::findOrFail($routeId);
        $route->update(['vehicle_id' => $vehicleId, 'driver_id' => $driverId]);
        Vehicle::where('id', $vehicleId)->update(['status' => 'on_route', 'driver_id' => $driverId]);

        return $route->fresh();
    }

    public function startRoute(int $routeId): DeliveryRoute
    {
        $route = DeliveryRoute::findOrFail($routeId);

        if ($route->status !== 'planned') {
            throw new \InvalidArgumentException("Cannot start route with status '{$route->status}'.");
        }

        $route->update(['status' => 'in_progress']);

        if ($route->vehicle_id) {
            Vehicle::where('id', $route->vehicle_id)->update(['status' => 'on_route']);
        }

        Log::info("RouteOptimization: Route {$route->reference} started.", ['driver_id' => $route->driver_id]);

        return $route->fresh();
    }

    public function completeStop(int $stopId, array $proofData): RouteStop
    {
        $stop = RouteStop::findOrFail($stopId);

        $stop->update([
            'status'            => 'completed',
            'actual_arrival'    => $proofData['actual_arrival'] ?? now(),
            'proof_of_delivery' => $proofData['proof_of_delivery'] ?? null,
            'signature_url'     => $proofData['signature_url'] ?? null,
            'notes'             => $proofData['notes'] ?? $stop->notes,
        ]);

        return $stop->fresh();
    }

    public function completeRoute(int $routeId): DeliveryRoute
    {
        $route = DeliveryRoute::with('stops')->findOrFail($routeId);

        if ($route->status !== 'in_progress') {
            throw new \InvalidArgumentException("Cannot complete route with status '{$route->status}'.");
        }

        $route->update(['status' => 'completed']);
        $route->stops()->where('status', 'pending')->update(['status' => 'skipped']);

        if ($route->vehicle_id) {
            Vehicle::where('id', $route->vehicle_id)->update(['status' => 'available']);
        }

        return $route->fresh();
    }

    /**
     * Driver mobile view: today stops.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDriverRoutes(int $driverId, string $date): array
    {
        return DeliveryRoute::where('driver_id', $driverId)
            ->where('date', $date)
            ->whereIn('status', ['planned', 'in_progress'])
            ->with(['stops', 'vehicle'])
            ->get()
            ->map(fn(DeliveryRoute $route) => [
                'id'                => $route->id,
                'reference'         => $route->reference,
                'name'              => $route->name,
                'status'            => $route->status,
                'status_color'      => $route->status_color,
                'total_stops'       => $route->total_stops,
                'completed_stops'   => $route->stops->where('status', 'completed')->count(),
                'total_distance_km' => $route->total_distance_km,
                'vehicle'           => $route->vehicle ? [
                    'name'         => $route->vehicle->name,
                    'plate_number' => $route->vehicle->plate_number,
                    'type'         => $route->vehicle->type,
                ] : null,
                'stops' => $route->stops->map(fn(RouteStop $s) => [
                    'id'              => $s->id,
                    'sequence'        => $s->sequence,
                    'type'            => $s->type,
                    'address'         => $s->address,
                    'lat'             => $s->lat,
                    'lng'             => $s->lng,
                    'planned_arrival' => $s->planned_arrival,
                    'status'          => $s->status,
                    'is_late'         => $s->isLate(),
                ])->toArray(),
            ])
            ->toArray();
    }

    /**
     * Route KPIs (on-time %, avg stops/route, distance, XOF cost).
     *
     * @return array<string, mixed>
     */
    public function getRouteKpis(int $companyId, string $period): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $routes = DeliveryRoute::where('company_id', $companyId)
            ->whereBetween('date', [$start, $end])
            ->where('status', 'completed')
            ->withCount([
                'stops',
                'stops as completed_stops_count' => fn($q) => $q->where('status', 'completed'),
            ])
            ->get();

        $totalRoutes     = $routes->count();
        $totalStops      = $routes->sum('stops_count');
        $completedStops  = $routes->sum('completed_stops_count');
        $totalDistanceKm = $routes->sum('total_distance_km');

        $onTimeCount = 0;
        foreach ($routes as $route) {
            $lateStops = $route->stops()
                ->where('status', 'completed')
                ->whereNotNull('actual_arrival')
                ->whereNotNull('planned_arrival')
                ->whereRaw(
                    'actual_arrival > CONCAT(DATE(actual_arrival), " ", planned_arrival)'
                )
                ->count();

            if ($lateStops === 0 && $route->stops_count > 0) {
                $onTimeCount++;
            }
        }

        return [
            'on_time_rate'        => $totalRoutes > 0 ? round(($onTimeCount / $totalRoutes) * 100, 1) : 0.0,
            'avg_stops_per_route' => $totalRoutes > 0 ? round($totalStops / $totalRoutes, 1) : 0.0,
            'avg_distance_km'     => $totalRoutes > 0 ? round((float) $totalDistanceKm / $totalRoutes, 2) : 0.0,
            'total_routes'        => $totalRoutes,
            'total_stops'         => $totalStops,
            'completed_stops'     => $completedStops,
            'cost_per_km_xof'     => 200.0,
            'total_cost_xof'      => round((float) $totalDistanceKm * 200.0, 0),
            'period'              => $period,
        ];
    }

    /**
     * Suggest best vehicle by weight/volume/availability.
     *
     * @return array<string, mixed>|null
     */
    public function suggestVehicle(int $routeId): ?array
    {
        $route = DeliveryRoute::with('stops.shipment')->findOrFail($routeId);

        $totalWeightKg = $route->stops
            ->filter(fn($s) => $s->shipment !== null)
            ->sum(fn($s) => (float) ($s->shipment->total_weight_kg ?? 0));

        $available = Vehicle::where('company_id', $route->company_id)
            ->where('status', 'available')
            ->where('max_weight_kg', '>=', $totalWeightKg)
            ->orderBy('max_weight_kg')
            ->first();

        if ($available === null) {
            return null;
        }

        return [
            'vehicle_id'    => $available->id,
            'vehicle_name'  => $available->name,
            'plate_number'  => $available->plate_number,
            'type'          => $available->type,
            'max_weight_kg' => $available->max_weight_kg,
            'reason'        => "Vehicule le plus adapte: capacite {$available->max_weight_kg} kg pour charge estimee {$totalWeightKg} kg",
        ];
    }

    /**
     * Haversine distance between two lat/lng points in km.
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat        = deg2rad($lat2 - $lat1);
        $dLng        = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Nearest-neighbour TSP heuristic.
     *
     * @param  array<int, array<string, mixed>> $stops
     * @return array<int, array<string, mixed>>
     */
    private function nearestNeighbourOrder(array $stops): array
    {
        $withCoords    = array_values(array_filter($stops, fn($s) => $s['lat'] !== null && $s['lng'] !== null));
        $withoutCoords = array_values(array_filter($stops, fn($s) => $s['lat'] === null || $s['lng'] === null));

        if (count($withCoords) <= 1) {
            return array_merge($withCoords, $withoutCoords);
        }

        $ordered   = [$withCoords[0]];
        $remaining = array_slice($withCoords, 1);

        while (! empty($remaining)) {
            $last       = end($ordered);
            $nearest    = null;
            $nearestIdx = 0;
            $minDist    = PHP_FLOAT_MAX;

            foreach ($remaining as $idx => $stop) {
                $dist = $this->calculateDistance(
                    (float) $last['lat'], (float) $last['lng'],
                    (float) $stop['lat'], (float) $stop['lng']
                );
                if ($dist < $minDist) {
                    $minDist    = $dist;
                    $nearest    = $stop;
                    $nearestIdx = $idx;
                }
            }

            if ($nearest !== null) {
                $ordered[] = $nearest;
                array_splice($remaining, $nearestIdx, 1);
            }
        }

        return array_merge($ordered, $withoutCoords);
    }

    /**
     * @param  array<int, array<string, mixed>> $stops
     */
    private function calculateTotalDistance(array $stops): float
    {
        $total = 0.0;
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $a = $stops[$i];
            $b = $stops[$i + 1];
            if ($a['lat'] !== null && $b['lat'] !== null) {
                $total += $this->calculateDistance(
                    (float) $a['lat'], (float) $a['lng'],
                    (float) $b['lat'], (float) $b['lng']
                );
            }
        }

        return $total;
    }

    /** @return array{0: string, 1: string} */
    private function parsePeriod(string $period): array
    {
        return match ($period) {
            'today'      => [now()->toDateString(), now()->toDateString()],
            'this_week'  => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'this_month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'last_month' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'this_year'  => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default      => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }
}
