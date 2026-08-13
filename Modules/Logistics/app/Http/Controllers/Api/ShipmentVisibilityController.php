<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\ShipmentTrackingEvent;
use Modules\Logistics\Services\ShipmentVisibility\FallbackTrackingConnector;
use Modules\Logistics\Services\ShipmentVisibility\FlexportConnector;
use Modules\Logistics\Services\ShipmentVisibility\FlightAwareConnector;
use Modules\Logistics\Services\ShipmentVisibility\MarineTrafficConnector;
use Modules\Logistics\Services\ShipmentVisibilityService;

/**
 * @group Logistics - Shipment Visibility
 */
class ShipmentVisibilityController extends Controller
{
    private function service(): ShipmentVisibilityService
    {
        return new ShipmentVisibilityService(
            new MarineTrafficConnector(config('services.marinetraffic', [])),
            new FlexportConnector(config('services.flexport', [])),
            new FlightAwareConnector(config('services.flightaware', [])),
            new FallbackTrackingConnector(),
        );
    }

    /**
     * GET /logistics/shipments/{id}/visibility
     * Get aggregated real-time tracking visibility for a shipment.
     */
    public function visibility(int $id): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);

        $payload = [
            'id'              => $shipment->id,
            'transport_mode'  => $shipment->transport_mode ?? 'unknown',
            'tracking_number' => $shipment->tracking_number ?? null,
            'carrier_slug'    => strtolower($shipment->carrier?->name ?? ''),
            'mmsi'            => $shipment->mmsi ?? null,
            'flight_number'   => $shipment->flight_number ?? null,
            'flexport_id'     => $shipment->flexport_id ?? null,
        ];

        $visibility = $this->service()->getVisibility($payload);

        return response()->json(array_merge($visibility, [
            'shipment_id' => $shipment->id,
            'reference'   => $shipment->reference ?? null,
        ]));
    }

    /**
     * POST /logistics/shipments/{id}/refresh-tracking
     * Force-refresh tracking data (bypass cache).
     */
    public function refreshTracking(int $id): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);

        $payload = [
            'id'              => $shipment->id,
            'transport_mode'  => $shipment->transport_mode ?? 'unknown',
            'tracking_number' => $shipment->tracking_number ?? null,
            'carrier_slug'    => strtolower($shipment->carrier?->name ?? ''),
            'mmsi'            => $shipment->mmsi ?? null,
            'flight_number'   => $shipment->flight_number ?? null,
            'flexport_id'     => $shipment->flexport_id ?? null,
        ];

        $visibility = $this->service()->refreshTracking($payload);

        return response()->json(array_merge($visibility, [
            'shipment_id' => $shipment->id,
            'refreshed_at' => now()->toIso8601String(),
        ]));
    }

    /**
     * GET /logistics/shipments/{id}/tracking-events
     * List all persisted tracking events for a shipment.
     */
    public function trackingEvents(Request $request, int $id): JsonResponse
    {
        Shipment::findOrFail($id);

        $events = ShipmentTrackingEvent::where('shipment_id', $id)
            ->orderByDesc('event_at')
            ->paginate(50);

        return response()->json($events);
    }
}
